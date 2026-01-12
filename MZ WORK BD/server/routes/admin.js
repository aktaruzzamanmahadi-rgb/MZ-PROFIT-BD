const express = require('express');
const { auth, adminOnly } = require('../middleware/auth');
const db = require('../config/database');

const router = express.Router();

// Approve transaction
router.post('/approve-transaction', auth, adminOnly, async (req, res) => {
  try {
    const { trx_id, admin_notes } = req.body;
    const conn = await db.getConnection();

    // Get transaction
    const [transactions] = await conn.query('SELECT user_id, amount, trx_type, status FROM transactions WHERE trx_id = ?', [trx_id]);
    if (transactions.length === 0) {
      conn.release();
      return res.status(404).json({ error: 'Transaction not found' });
    }

    const trx = transactions[0];
    if (trx.status !== 'pending') {
      conn.release();
      return res.status(400).json({ error: 'Transaction already processed' });
    }

    // Update transaction
    await conn.query(
      'UPDATE transactions SET status = "approved", admin_notes = ?, verified_by = ?, verified_at = NOW() WHERE trx_id = ?',
      [admin_notes, req.user.user_id, trx_id]
    );

    // Add to wallet if deposit
    if (trx.trx_type === 'deposit') {
      await conn.query(
        'UPDATE users SET wallet_balance = wallet_balance + ? WHERE user_id = ?',
        [trx.amount, trx.user_id]
      );
    } else if (trx.trx_type === 'withdrawal') {
      // Deduct from wallet if withdrawal
      await conn.query(
        'UPDATE users SET wallet_balance = wallet_balance - ? WHERE user_id = ?',
        [trx.amount, trx.user_id]
      );
    }

    // Log admin action
    await conn.query(
      'INSERT INTO admin_logs (admin_id, action, details, target_user_id) VALUES (?, "approve_transaction", ?, ?)',
      [req.user.user_id, `Approved transaction ${trx_id}`, trx.user_id]
    );

    conn.release();

    res.json({ message: 'Transaction approved', trx_id });
  } catch (err) {
    res.status(500).json({ error: 'Failed to approve transaction', message: err.message });
  }
});

// Verify NID
router.post('/verify-nid', auth, adminOnly, async (req, res) => {
  try {
    const { user_id } = req.body;
    const conn = await db.getConnection();

    await conn.query(
      'UPDATE users SET nid_verified = TRUE WHERE user_id = ?',
      [user_id]
    );

    await conn.query(
      'INSERT INTO admin_logs (admin_id, action, details, target_user_id) VALUES (?, "verify_nid", "NID verified", ?)',
      [req.user.user_id, user_id]
    );

    conn.release();

    res.json({ message: 'NID verified', user_id });
  } catch (err) {
    res.status(500).json({ error: 'Failed to verify NID', message: err.message });
  }
});

// Get pending verifications
router.get('/pending', auth, adminOnly, async (req, res) => {
  try {
    const conn = await db.getConnection();

    const [pendingTransactions] = await conn.query(
      'SELECT trx_id, user_id, amount, payment_method, payment_trx_id, created_at FROM transactions WHERE status = "pending" ORDER BY created_at ASC'
    );

    const [pendingNids] = await conn.query(
      'SELECT user_id, username, nid FROM users WHERE nid IS NOT NULL AND nid_verified = FALSE'
    );

    conn.release();

    res.json({
      pending_transactions: pendingTransactions,
      pending_nid_verifications: pendingNids
    });
  } catch (err) {
    res.status(500).json({ error: 'Failed to fetch pending items', message: err.message });
  }
});

module.exports = router;
