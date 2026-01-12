const express = require('express');
const { auth } = require('../middleware/auth');
const db = require('../config/database');

const router = express.Router();

// Get active ads with daily limit check
router.get('/', auth, async (req, res) => {
  try {
    const conn = await db.getConnection();
    const userId = req.user.user_id;
    const today = new Date().toISOString().split('T')[0];

    // Get today's click count
    const [dailyTracking] = await conn.query(
      'SELECT clicks_today FROM daily_ad_tracking WHERE user_id = ? AND ad_date = ?',
      [userId, today]
    );

    const clicksToday = dailyTracking.length > 0 ? dailyTracking[0].clicks_today : 0;
    const canClickMore = clicksToday < 5;

    // Get active ads
    const [ads] = await conn.query(
      'SELECT ad_id, company_id, title, description, target_url, reward_value, clicks_count FROM ads WHERE status = "active" LIMIT 20'
    );

    conn.release();

    res.json({
      ads,
      daily_limit: 5,
      clicks_today: clicksToday,
      can_click_more: canClickMore
    });
  } catch (err) {
    res.status(500).json({ error: 'Failed to fetch ads', message: err.message });
  }
});

// Initialize ad click session (30-second server-side timer)
router.post('/click-session', auth, async (req, res) => {
  try {
    const { ad_id } = req.body;
    const userId = req.user.user_id;
    const sessionId = `${userId}-${ad_id}-${Date.now()}`;
    const today = new Date().toISOString().split('T')[0];

    const conn = await db.getConnection();

    // Check today's click limit
    const [dailyTracking] = await conn.query(
      'SELECT clicks_today FROM daily_ad_tracking WHERE user_id = ? AND ad_date = ?',
      [userId, today]
    );

    const clicksToday = dailyTracking.length > 0 ? dailyTracking[0].clicks_today : 0;

    if (clicksToday >= 5) {
      conn.release();
      return res.status(403).json({ error: 'Daily ad limit (5) reached' });
    }

    // Check VPN/IP changes
    const userIp = req.ip;
    const [users] = await conn.query('SELECT last_ip_address FROM users WHERE user_id = ?', [userId]);
    const lastIp = users[0].last_ip_address;

    if (lastIp && lastIp !== userIp) {
      console.warn(`IP change detected for user ${userId}: ${lastIp} -> ${userIp}`);
    }

    conn.release();

    // Session expires in 60 seconds (30s ad + 30s buffer)
    const expiresAt = Date.now() + 60000;

    res.json({
      session_id: sessionId,
      ad_id,
      timer_duration: 30,
      expires_at: expiresAt,
      message: 'Session started. Keep the tab open for 30 seconds.'
    });
  } catch (err) {
    res.status(500).json({ error: 'Failed to create session', message: err.message });
  }
});

// Complete ad click (submit after 30 seconds)
router.post('/click-complete', auth, async (req, res) => {
  try {
    const { session_id, ad_id } = req.body;
    const userId = req.user.user_id;
    const userIp = req.ip;
    const today = new Date().toISOString().split('T')[0];

    const conn = await db.getConnection();

    // Double-check daily limit
    const [dailyTracking] = await conn.query(
      'SELECT clicks_today FROM daily_ad_tracking WHERE user_id = ? AND ad_date = ?',
      [userId, today]
    );

    const clicksToday = dailyTracking.length > 0 ? dailyTracking[0].clicks_today : 0;

    if (clicksToday >= 5) {
      conn.release();
      return res.status(403).json({ error: 'Daily limit reached' });
    }

    // Check if ad exists
    const [ads] = await conn.query('SELECT reward_value FROM ads WHERE ad_id = ? AND status = "active"', [ad_id]);
    if (ads.length === 0) {
      conn.release();
      return res.status(404).json({ error: 'Ad not found or inactive' });
    }

    const rewardValue = ads[0].reward_value;

    // Record click
    await conn.query(
      'INSERT INTO click_logs (user_id, ad_id, session_id, server_completed, user_ip_address) VALUES (?, ?, ?, TRUE, ?)',
      [userId, ad_id, session_id, userIp]
    );

    // Update daily tracking
    if (dailyTracking.length > 0) {
      await conn.query(
        'UPDATE daily_ad_tracking SET clicks_today = clicks_today + 1 WHERE user_id = ? AND ad_date = ?',
        [userId, today]
      );
    } else {
      await conn.query(
        'INSERT INTO daily_ad_tracking (user_id, ad_date, clicks_today) VALUES (?, ?, 1)',
        [userId, today]
      );
    }

    // Update ad click count
    await conn.query('UPDATE ads SET clicks_count = clicks_count + 1 WHERE ad_id = ?', [ad_id]);

    // Every 30 clicks = 5 TK earned
    const clicksAfter = dailyTracking.length > 0 ? clicksToday + 1 : 1;
    const earning = Math.floor(clicksAfter / 30) * 5; // Every 30 clicks = 5 TK

    if (earning > 0) {
      await conn.query('UPDATE users SET wallet_balance = wallet_balance + ? WHERE user_id = ?', [earning, userId]);

      // Log earning transaction
      await conn.query(
        'INSERT INTO transactions (user_id, amount, trx_type, payment_method, status) VALUES (?, ?, "earning", "system", "approved")',
        [userId, earning]
      );
    }

    // Update last IP
    await conn.query('UPDATE users SET last_ip_address = ? WHERE user_id = ?', [userIp, userId]);

    conn.release();

    res.json({
      message: 'Click recorded successfully',
      reward_earned: rewardValue,
      total_earned_today: earning > 0 ? earning : 0,
      clicks_today: clicksAfter
    });
  } catch (err) {
    res.status(500).json({ error: 'Failed to record click', message: err.message });
  }
});

module.exports = router;
