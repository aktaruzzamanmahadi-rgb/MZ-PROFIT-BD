const express = require('express');
const { auth } = require('../middleware/auth');
const db = require('../config/database');

const router = express.Router();

// Get user profile
router.get('/profile', auth, async (req, res) => {
  try {
    const conn = await db.getConnection();
    const [users] = await conn.query(
      'SELECT user_id, username, email, phone, role, wallet_balance, nid_verified, account_status, created_at FROM users WHERE user_id = ?',
      [req.user.user_id]
    );
    conn.release();

    if (users.length === 0) {
      return res.status(404).json({ error: 'User not found' });
    }

    res.json(users[0]);
  } catch (err) {
    res.status(500).json({ error: 'Failed to fetch profile', message: err.message });
  }
});

// Update profile
router.put('/profile', auth, async (req, res) => {
  try {
    const { phone } = req.body;
    const conn = await db.getConnection();

    await conn.query('UPDATE users SET phone = ? WHERE user_id = ?', [phone, req.user.user_id]);
    conn.release();

    res.json({ message: 'Profile updated successfully' });
  } catch (err) {
    res.status(500).json({ error: 'Failed to update profile', message: err.message });
  }
});

// Verify NID (Submit for verification)
router.post('/verify-nid', auth, async (req, res) => {
  try {
    const { nid } = req.body;
    const conn = await db.getConnection();

    await conn.query('UPDATE users SET nid = ? WHERE user_id = ?', [nid, req.user.user_id]);
    conn.release();

    res.json({
      message: 'NID submitted for verification. Admin will review within 24-48 hours.',
      status: 'pending'
    });
  } catch (err) {
    res.status(500).json({ error: 'Failed to submit NID', message: err.message });
  }
});

// Get wallet balance
router.get('/wallet', auth, async (req, res) => {
  try {
    const conn = await db.getConnection();
    const [users] = await conn.query('SELECT wallet_balance FROM users WHERE user_id = ?', [req.user.user_id]);
    conn.release();

    res.json({ wallet_balance: users[0].wallet_balance });
  } catch (err) {
    res.status(500).json({ error: 'Failed to fetch wallet', message: err.message });
  }
});

module.exports = router;
