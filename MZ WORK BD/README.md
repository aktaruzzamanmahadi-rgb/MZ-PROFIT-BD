# MZ Profit BD - PHP Backend

## Setup & Installation

### 1. Database Setup
```bash
mysql -u root -p < database/schema.sql
```

### 2. PHP Requirements
- PHP 7.4+ with MySQLi extension
- MySQL 8.0+

### 3. Configuration
Copy `.env` file in server root and update your database credentials:
```
DB_HOST=localhost
DB_USER=root
DB_PASSWORD=your_password
DB_NAME=mz_profit_bd
```

### 4. Run Server (Built-in PHP server)
```bash
cd server
php -S localhost:8000
```

The API will be available at: `http://localhost:8000/index.php`

---

## API Endpoints

### Authentication
- `POST /api/auth?action=register` - Register new user
- `POST /api/auth?action=login` - Login and get JWT token

### Users
- `GET /api/users?action=profile` - Get user profile
- `PUT /api/users?action=profile` - Update profile
- `POST /api/users?action=verify-nid` - Submit NID for verification
- `GET /api/users?action=wallet` - Get wallet balance

### Ads & Click to Earn
- `GET /api/ads?action=list` - Get available ads
- `POST /api/ads?action=click-session` - Initialize 30-second ad session
- `POST /api/ads?action=click-complete` - Complete ad click

### Transactions
- `POST /api/transactions?action=deposit` - Submit deposit request
- `GET /api/transactions?action=list` - Get transaction history
- `POST /api/transactions?action=withdraw` - Request withdrawal

### Campaigns
- `GET /api/campaigns?action=list` - Get active campaigns
- `POST /api/campaigns?action=invest` - Invest in campaign

### Admin (Requires admin role)
- `GET /api/admin?action=pending` - Get pending verifications
- `POST /api/admin?action=approve-transaction` - Approve transaction
- `POST /api/admin?action=verify-nid` - Verify user NID

---

## Key Features

✓ **30 Ads = 5 TK** earning logic
✓ **5 Daily Ads** limit per user
✓ **Server-side 30-second timer** (prevents cheating)
✓ **VPN/IP tracking** for security
✓ **JWT Authentication**
✓ **Admin verification** system for NIDs & transactions
✓ **Wallet system** with balance management
✓ **Campaign investment** system

---

## Frontend Setup

See `client/` folder for React frontend implementation.
