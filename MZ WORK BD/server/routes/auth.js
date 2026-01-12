const express = require('express');
const bcrypt = require('bcryptjs');
const jwt = require('jsonwebtoken');
const Joi = require('joi');
const db = require('../config/database');

const router = express.Router();

// Validation schema
const registerSchema = Joi.object({
  username: Joi.string().alphanum().min(3).max(30).required(),
  email: Joi.string().email().required(),
  password: Joi.string().min(6).required(),
  phone: Joi.string(),
  role: Joi.string().valid('investor', 'company').default('investor')
});

const loginSchema = Joi.object({
  email: Joi.string().email().required(),
  password: Joi.string().required()
});

// Register
router.post('/register', async (req, res) => {
  try {
    const { error, value } = registerSchema.validate(req.body);
    if (error) {
      return res.status(400).json({ error: error.details[0].message });
    }

    const conn = await db.getConnection();
    const { username, email, password, phone, role } = value;

    // Check if user exists
    const [existing] = await conn.query('SELECT user_id FROM users WHERE email = ?', [email]);
    if (existing.length > 0) {
      conn.release();
      return res.status(400).json({ error: 'User already exists' });
    }

    // Hash password
    const hashedPassword = await bcrypt.hash(password, 10);

    // Create user
    const [result] = await conn.query(
      'INSERT INTO users (username, email, password_hash, phone, role) VALUES (?, ?, ?, ?, ?)',
      [username, email, hashedPassword, phone, role]
    );

    conn.release();

    res.status(201).json({
      message: 'User registered successfully',
      user_id: result.insertId,
      username,
      email,
      role
    });
  } catch (err) {
    res.status(500).json({ error: 'Registration failed', message: err.message });
  }
});

// Login
router.post('/login', async (req, res) => {
  try {
    const { error, value } = loginSchema.validate(req.body);
    if (error) {
      return res.status(400).json({ error: error.details[0].message });
    }

    const conn = await db.getConnection();
    const { email, password } = value;

    // Find user
    const [users] = await conn.query('SELECT * FROM users WHERE email = ?', [email]);
    if (users.length === 0) {
      conn.release();
      return res.status(401).json({ error: 'Invalid credentials' });
    }

    const user = users[0];

    // Check password
    const validPassword = await bcrypt.compare(password, user.password_hash);
    if (!validPassword) {
      conn.release();
      return res.status(401).json({ error: 'Invalid credentials' });
    }

    conn.release();

    // Generate JWT
    const token = jwt.sign(
      { user_id: user.user_id, email: user.email, role: user.role },
      process.env.JWT_SECRET,
      { expiresIn: process.env.JWT_EXPIRY || '7d' }
    );

    res.json({
      message: 'Login successful',
      token,
      user: {
        user_id: user.user_id,
        username: user.username,
        email: user.email,
        role: user.role,
        wallet_balance: user.wallet_balance
      }
    });
  } catch (err) {
    res.status(500).json({ error: 'Login failed', message: err.message });
  }
});

module.exports = router;
