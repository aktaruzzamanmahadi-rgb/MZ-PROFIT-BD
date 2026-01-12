const express = require('express');
const { auth } = require('../middleware/auth');
const db = require('../config/database');

const router = express.Router();

// Submit deposit request
router.post('/deposit', auth, async (req, res) => {
  try {
    const { amount, payment_method, payment_trx_id } = req.body;
    const conn = await db.getConnection();

    const [result] = await conn.query(
      'INSERT INTO transactions (user_id, amount, trx_type, payment_method, payment_trx_id, status) VALUES (?, ?, "deposit", ?, ?, "pending")',
      [req.user.user_id, amount, payment_method, payment_trx_id]
    );

    conn.release();

    res.status(201).json({
      message: 'Deposit request submitted. Admin will verify within 48-72 hours.',
      trx_id: result.insertId,
      status: 'pending'
    });
  } catch (err) {
    res.status(500).json({ error: 'Failed to submit deposit', message: err.message });
  }
});

// Get user transactions
router.get('/', auth, async (req, res) => {
  try {
    const conn = await db.getConnection();
    const [transactions] = await conn.query(
      'SELECT trx_id, amount, trx_type, payment_method, status, created_at FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 50',
      [req.user.user_id]
    );
    conn.release();

    res.json(transactions);
  } catch (err) {
    res.status(500).json({ error: 'Failed to fetch transactions', message: err.message });
  }
});

// Request withdrawal
router.post('/withdraw', auth, async (req, res) => {
  try {
    const { amount, payment_method, payment_trx_id } = req.body;
    const conn = await db.getConnection();

    // Check wallet balance
    const [users] = await conn.query('SELECT wallet_balance FROM users WHERE user_id = ?', [req.user.user_id]);
    if (users[0].wallet_balance < amount) {
      conn.release();
      return res.status(400).json({ error: 'Insufficient wallet balance' });
    }

    // Create withdrawal request
    const [result] = await conn.query(
      'INSERT INTO transactions (user_id, amount, trx_type, payment_method, payment_trx_id, status) VALUES (?, ?, "withdrawal", ?, ?, "pending")',
      [req.user.user_id, amount, payment_method, payment_trx_id]
    );

    conn.release();

    res.json({
      message: 'Withdrawal request submitted. Admin will process within 48 hours.',
      trx_id: result.insertId,
      status: 'pending'
    });
  } catch (err) {
    res.status(500).json({ error: 'Failed to request withdrawal', message: err.message });
  }
});

module.exports = router;
