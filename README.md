# Paystack + Telegram backend
Ready-to-deploy Node.js backend to accept Paystack payments from a Telegram bot. Designed for GitHub + Railway.

## Features
- Initialize Paystack payment and redirect user to Paystack payment page (/pay)
- Verify payment after redirect (/verify) and notify Telegram user
- Optional webhook endpoint (/webhook/paystack) for server-side confirmation
- Lightweight JSON transaction store

## Setup (local)
1. Copy repository files and run:
   ```
   npm install
   cp .env.example .env
   ```
   Edit `.env` with your credentials.

2. Start:
   ```
   npm start
   ```

3. Open `http://localhost:3000/` to confirm.

## Integration with your Telegram bot
From your Telegram bot code (PHP or Node), send a message with an inline button that opens:
```
https://<HOST>/pay?chat_id=<TELEGRAM_CHAT_ID>&amount=2000&email=user@example.com
```
- `chat_id` is required so the backend can send notification to the right user.
- `amount` is in NGN (e.g., 2000).
- Optional: `reference` to pass your own reference string.

When user completes payment, Paystack will redirect them to:
```
https://<HOST>/verify?reference=PAYSTACK_REFERENCE
```
Your server will verify the payment and send a Telegram message to the `chat_id`.

## Deploy to GitHub and Railway
1. Create a new GitHub repo and push code.
2. Create a new Railway project and connect it to your GitHub repo.
3. In Railway dashboard, add environment variables:
   - `PAYSTACK_SECRET`
   - `TELEGRAM_BOT_TOKEN`
   - `HOST_URL` (the Railway app URL; set after deployment or in Railway's domains)
   - `ADMIN_SECRET` (choose a strong secret)
4. Deploy. Railway will build and run the app.

## Configure Paystack
- In Paystack dashboard > Settings > Callback URL, add:
  ```
  https://<HOST>/verify
  ```
- (Optional) Configure webhook to:
  ```
  https://<HOST>/webhook/paystack
  ```
  and enable the events you want (transaction.success). Implement signature verification for production.

## Security notes
- Do NOT commit `.env` or `transactions.json`.
- For webhook endpoints, always verify Paystack signature header (x-paystack-signature) in production.
- Protect admin endpoints using strong secrets or authentication.

## Next steps I can add for you
- Save transactions to PostgreSQL (Railway add-on) instead of JSON.
- Implement signature verification for Paystack webhooks.
- Add two-step confirmation inside Telegram (confirm purchase after user clicks /buy).
- Build an admin UI to view and search transactions.
