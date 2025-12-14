// api/admin.js - Admin Panel Serverless Function
const mysql = require('serverless-mysql');
const bcrypt = require('bcryptjs');
const jwt = require('jsonwebtoken');

const db = mysql({
    config: {
        host: 'sql211.infinityfree.com',
        port: 3306,
        database: 'if0_40677908_astradb1',
        user: 'if0_40677908',
        password: '23022Cm032'
    }
});

const JWT_SECRET = process.env.JWT_SECRET || 'mydrive_secret_2025';

async function getUserFromToken(req) {
    const authHeader = req.headers.authorization || '';
    const token = authHeader.replace('Bearer ', '');

    if (!token) return null;

    try {
        const decoded = jwt.verify(token, JWT_SECRET);
        return decoded.userId;
    } catch {
        return null;
    }
}

module.exports = async (req, res) => {
    res.setHeader('Access-Control-Allow-Origin', '*');
    res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
    res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');

    if (req.method === 'OPTIONS') {
        return res.status(200).end();
    }

    const userId = await getUserFromToken(req);
    if (!userId) {
        return res.status(401).json({ error: 'Unauthorized' });
    }

    try {
        // Check if admin
        const users = await db.query('SELECT is_admin FROM users WHERE user_id = ?', [userId]);
        const user = users[0];

        if (!user || !user.is_admin) {
            await db.end();
            return res.status(403).json({ error: 'Admin access required' });
        }

        // ====================================================================
        // LIST USERS
        // ====================================================================
        if (req.method === 'GET' && req.query.action === 'list-users') {
            const allUsers = await db.query(
                'SELECT user_id, email, storage_used, storage_limit, is_admin, created_at FROM users ORDER BY created_at DESC'
            );

            await db.end();
            return res.status(200).json({ users: allUsers });
        }

        // ====================================================================
        // CREATE USER
        // ====================================================================
        if (req.method === 'POST' && req.body.action === 'create-user') {
            const { userId: newUserId, email } = req.body;

            if (!newUserId || !email) {
                await db.end();
                return res.status(400).json({ error: 'User ID and email required' });
            }

            // Generate temporary password DD:MM:YY-HH:MM
            const now = new Date();
            const day = String(now.getDate()).padStart(2, '0');
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const year = String(now.getFullYear()).slice(-2);
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const tempPassword = `${day}:${month}:${year}-${hours}:${minutes}`;

            const passwordHash = await bcrypt.hash(tempPassword, 10);

            await db.query(
                'INSERT INTO users (user_id, email, password_hash, temp_password, must_reset_password, is_admin) VALUES (?, ?, ?, ?, 1, 0)',
                [newUserId, email, passwordHash, tempPassword]
            );

            await db.query(
                'INSERT INTO activity_logs (user_id, action, details) VALUES (?, ?, ?)',
                [userId, 'USER_CREATED', `Created user ${newUserId}`]
            );

            await db.end();
            return res.status(201).json({
                message: 'User created successfully',
                userId: newUserId,
                tempPassword
            });
        }

        await db.end();
        return res.status(400).json({ error: 'Invalid request' });

    } catch (error) {
        console.error('Admin error:', error);
        await db.end();

        if (error.code === 'ER_DUP_ENTRY') {
            return res.status(409).json({ error: 'User ID or email already exists' });
        }

        return res.status(500).json({ error: 'Server error' });
    }
};
