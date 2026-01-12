const express = require('express');
const { auth } = require('../middleware/auth');
const db = require('../config/database');

const router = express.Router();

// Get all active campaigns
router.get('/', auth, async (req, res) => {
  try {
    const conn = await db.getConnection();
    const [campaigns] = await conn.query(
      'SELECT c.campaign_id, c.title, c.description, c.target_amount, c.raised_amount, c.category, c.status, u.username FROM campaigns c JOIN users u ON c.company_id = u.user_id WHERE c.status = "active" LIMIT 50'
    );
    conn.release();

    res.json(campaigns);
  } catch (err) {
    res.status(500).json({ error: 'Failed to fetch campaigns', message: err.message });
  }
});

// Invest in campaign
router.post('/invest', auth, async (req, res) => {
  try {
    const { campaign_id, amount } = req.body;
    const conn = await db.getConnection();

    // Check wallet balance
    const [users] = await conn.query('SELECT wallet_balance FROM users WHERE user_id = ?', [req.user.user_id]);
    if (users[0].wallet_balance < amount) {
      conn.release();
      return res.status(400).json({ error: 'Insufficient wallet balance' });
    }

    // Create investment
    const [result] = await conn.query(
      'INSERT INTO investments (investor_id, campaign_id, amount, status) VALUES (?, ?, ?, "approved")',
      [req.user.user_id, campaign_id, amount]
    );

    // Deduct from wallet
    await conn.query(
      'UPDATE users SET wallet_balance = wallet_balance - ? WHERE user_id = ?',
      [amount, req.user.user_id]
    );

    // Update campaign raised amount
    await conn.query(
      'UPDATE campaigns SET raised_amount = raised_amount + ? WHERE campaign_id = ?',
      [amount, campaign_id]
    );

    conn.release();

    res.status(201).json({
      message: 'Investment successful',
      investment_id: result.insertId,
      amount,
      status: 'approved'
    });
  } catch (err) {
    res.status(500).json({ error: 'Investment failed', message: err.message });
  }
});

module.exports = router;
