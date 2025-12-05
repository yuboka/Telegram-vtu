// server.js
// Minimal Paystack + Telegram payment backend
// Usage: set env vars (see .env.example)

const express = require('express');
const axios = require('axios');
const bodyParser = require('body-parser');
const fs = require('fs');
const { v4: uuidv4 } = require('uuid');

const app = express();
app.use(bodyParser.json());
app.use(bodyParser.urlencoded({ extended: true }));

const PORT = process.env.PORT || 3000;
const PAYSTACK_SECRET = process.env.PAYSTACK_SECRET || '';
const TELEGRAM_BOT_TOKEN = process.env.TELEGRAM_BOT_TOKEN || '';
const HOST_URL = process.env.HOST_URL || ''; // e.g. https://your-app.railway.app
const CALLBACK_PATH = process.env.CALLBACK_PATH || '/verify';
const CALLBACK_URL = `${HOST_URL}${CALLBACK_PATH}`;
const TRANSACTIONS_FILE = process.env.TRANSACTIONS_FILE || './transactions.json';

// Simple storage helpers ------------------------------------------------
function readTransactions() {
  try {
    if (!fs.existsSync(TRANSACTIONS_FILE)) return [];
    const raw = fs.readFileSync(TRANSACTIONS_FILE, 'utf8');
    return JSON.parse(raw || '[]');
  } catch (e) {
    console.error('readTransactions error', e);
    return [];
  }
}
function writeTransactions(arr) {
  fs.writeFileSync(TRANSACTIONS_FILE, JSON.stringify(arr, null, 2));
}
function addTransaction(tx) {
  const arr = readTransactions();
  arr.push(tx);
  writeTransactions(arr);
}

// Utility: send Telegram message
async function sendTelegramMessage(chat_id, text) {
  if (!TELEGRAM_BOT_TOKEN) {
    console.warn('TELEGRAM_BOT_TOKEN not set; skipping telegram message');
    return;
  }
  const url = `https://api.telegram.org/bot${TELEGRAM_BOT_TOKEN}/sendMessage`;
  try {
    await axios.post(url, { chat_id, text });
  } catch (err) {
    console.error('sendTelegramMessage error', err?.response?.data || err.message);
  }
}

// Endpoint: create/initiate payment
// Example usage (from your Telegram bot): GET /pay?chat_id=123456&amount=2000&email=user@example.com&reference=optionalRef
app.get('/pay', async (req, res) => {
  try {
    const chat_id = req.query.chat_id;
    if (!chat_id) return res.status(400).send('Missing chat_id');

    const amount = parseFloat(req.query.amount || '2000'); // NGN
    if (isNaN(amount) || amount <= 0) return res.status(400).send('Invalid amount');

    const email = req.query.email || `user_${chat_id}@example.com`;
    const metadata = {
      chat_id,
      // you can add more metadata like product id, plan_id, custom reference etc
      meta: req.query.meta || null
    };

    const reference = req.query.reference || `tg_${chat_id}_${Date.now()}`;

    const payload = {
      email,
      amount: Math.round(amount * 100), // Paystack expects kobo (cents)
      reference,
      metadata,
      callback_url: CALLBACK_URL
    };

    const initRes = await axios.post('https://api.paystack.co/transaction/initialize', payload, {
      headers: { Authorization: `Bearer ${PAYSTACK_SECRET}`, 'Content-Type': 'application/json' }
    });

    if (!initRes.data || !initRes.data.status) {
      return res.status(500).send('Failed to initialize Paystack transaction');
    }

    // Log transaction as 'initialised'
    const tx = {
      id: uuidv4(),
      reference,
      chat_id,
      email,
      amount,
      status: 'initialized',
      paystack_reference: initRes.data.data.reference || null,
      authorization_url: initRes.data.data.authorization_url,
      created_at: new Date().toISOString()
    };
    addTransaction(tx);

    // redirect the user to Paystack payment page
    return res.redirect(initRes.data.data.authorization_url);
  } catch (err) {
    console.error('Pay init error', err?.response?.data || err.message);
    return res.status(500).send('Error creating payment');
  }
});

// Endpoint: verify callback (Paystack will redirect here after payment)
// Paystack redirects users to callback_url with ?reference=xxxxx
app.get('/verify', async (req, res) => {
  try {
    const reference = req.query.reference;
    if (!reference) return res.status(400).send('Missing reference');

    // Verify with Paystack
    const verifyRes = await axios.get(`https://api.paystack.co/transaction/verify/${encodeURIComponent(reference)}`, {
      headers: { Authorization: `Bearer ${PAYSTACK_SECRET}` }
    });

    if (!verifyRes.data || !verifyRes.data.status) {
      return res.status(500).send('Verification failed');
    }

    const data = verifyRes.data.data;
    const metadata = data.metadata || {};
    const chat_id = metadata.chat_id || (metadata.meta && metadata.meta.chat_id);
    const amount = data.amount / 100;
    const status = data.status; // success | failed | abandoned
    const paystackRef = data.reference;

    // Update transaction record (if found)
    const txs = readTransactions();
    const idx = txs.findIndex(t => t.reference === reference || t.paystack_reference === paystackRef);
    if (idx >= 0) {
      txs[idx].status = status;
      txs[idx].paystack_data = data;
      txs[idx].verified_at = new Date().toISOString();
      writeTransactions(txs);
    } else {
      // create a record if not exists
      addTransaction({
        id: uuidv4(),
        reference,
        chat_id,
        email: data.customer?.email || null,
        amount,
        status,
        paystack_reference: paystackRef,
        paystack_data: data,
        created_at: new Date().toISOString()
      });
    }

    // Notify Telegram user
    if (chat_id) {
      const msg = status === 'success'
        ? `✅ Payment successful!\nReference: ${reference}\nAmount: ${amount}`
        : `⚠️ Payment not successful.\nReference: ${reference}\nStatus: ${status}`;
      await sendTelegramMessage(chat_id, msg);
    }

    // Show a friendly page to user
    return res.send(`<h3>Payment ${status}</h3><p>Reference: ${reference}</p><p>Amount: ${amount}</p><p>You can close this window.</p>`);
  } catch (err) {
    console.error('verify error', err?.response?.data || err.message);
    return res.status(500).send('Error verifying payment');
  }
});

// (Optional) Webhook endpoint that Paystack can post to (strongly recommended for server-side confirmation).
// Configure webhook URL in Paystack dashboard to: https://your-host/webhook/paystack
// Paystack signs webhook payloads with x-paystack-signature header. For production, verify signature.
app.post('/webhook/paystack', async (req, res) => {
  // NOTE: you should verify the x-paystack-signature header using your Paystack secret.
  // For brevity this demo does not verify the signature. Make sure to implement signature verification in production.
  try {
    const event = req.body;
    if (!event || !event.event) {
      return res.status(400).send('Invalid webhook payload');
    }

    // Example: handle charge.success event
    if (event.event === 'charge.success' || event.event === 'transaction.success') {
      const data = event.data || {};
      const reference = data.reference;
      const chat_id = data?.metadata?.chat_id;

      // update transaction
      const txs = readTransactions();
      const idx = txs.findIndex(t => t.reference === reference || t.paystack_reference === reference);
      if (idx >= 0) {
        txs[idx].status = 'success';
        txs[idx].paystack_data = data;
        txs[idx].verified_at = new Date().toISOString();
        writeTransactions(txs);
      } else {
        addTransaction({
          id: uuidv4(),
          reference,
          chat_id,
          email: data.customer?.email || null,
          amount: data.amount / 100,
          status: 'success',
          paystack_reference: reference,
          paystack_data: data,
          created_at: new Date().toISOString()
        });
      }

      if (chat_id) {
        await sendTelegramMessage(chat_id, `✅ Payment confirmed (webhook).\nReference: ${reference}`);
      }
    }

    // always respond 200 quickly
    res.json({ status: 'ok' });
  } catch (err) {
    console.error('webhook error', err?.message);
    res.status(500).send('webhook handler error');
  }
});

// Simple admin route to list recent transactions (PROTECT in production!)
app.get('/admin/transactions', (req, res) => {
  const secret = req.query.secret || '';
  if (secret !== (process.env.ADMIN_SECRET || 'admin_secret')) return res.status(403).send('Forbidden');
  res.json(readTransactions().slice(-200).reverse());
});

app.get('/', (req, res) => res.send('Paystack Telegram backend running'));
app.listen(PORT, () => console.log(`Server listening on ${PORT}`));
