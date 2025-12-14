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

const JWT_SECRET = 'mydrive_secret_2025';

const getUserFromToken = (req) => {
    const authHeader = req.headers.authorization || req.headers.Authorization || '';
    const token = authHeader.replace('Bearer ', '');

    if (!token) return null;

    try {
        const decoded = jwt.verify(token, JWT_SECRET);
        return decoded.userId;
    } catch {
        return null;
    }
};

export default async function handler(req, res) {
    // CORS
    res.setHeader('Access-Control-Allow-Origin', '*');
    res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
    res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');

    if (req.method === 'OPTIONS') {
        return res.status(200).end();
    }

    try {
        const { action, userId, password, newPassword } = req.body || {};

        // LOGIN
        if (action === 'login') {
            if (!userId || !password) {
                await db.end();
                return res.status(400).json({ error: 'User ID and password required' });
            }

            const users = await db.query('SELECT * FROM users WHERE user_id = ?', [userId]);

            if (!users || users.length === 0) {
                await db.end();
                return res.status(401).json({ error: 'Invalid credentials' });
            }

            const user = users[0];
            const validPassword = await bcrypt.compare(password, user.password_hash);

            if (!validPassword) {
                await db.end();
                return res.status(401).json({ error: 'Invalid credentials' });
            }

            // Log activity
            try {
                await db.query(
                    'INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)',
                    [userId, 'LOGIN', 'User logged in', req.headers['x-forwarded-for'] || 'unknown']
                );
            } catch (e) {
                console.log('Activity log error:', e);
            }

            const token = jwt.sign({ userId: user.user_id }, JWT_SECRET, { expiresIn: '7d' });

            await db.end();

            return res.status(200).json({
                token,
                userId: user.user_id,
                email: user.email,
                isAdmin: Boolean(user.is_admin),
                mustResetPassword: Boolean(user.must_reset_password),
                storageUsed: Number(user.storage_used),
                storageLimit: Number(user.storage_limit)
            });
        }

        // RESET PASSWORD
        if (action === 'reset-password') {
            const currentUserId = getUserFromToken(req);
            if (!currentUserId) {
                await db.end();
                return res.status(401).json({ error: 'Unauthorized' });
            }

            if (!newPassword || newPassword.length < 8) {
                await db.end();
                return res.status(400).json({ error: 'Password must be at least 8 characters' });
            }

            const passwordHash = await bcrypt.hash(newPassword, 10);

            await db.query(
                'UPDATE users SET password_hash = ?, must_reset_password = 0, temp_password = NULL WHERE user_id = ?',
                [passwordHash, currentUserId]
            );

            try {
                await db.query(
                    'INSERT INTO activity_logs (user_id, action, details) VALUES (?, ?, ?)',
                    [currentUserId, 'PASSWORD_RESET', 'User reset password']
                );
            } catch (e) {
                console.log('Activity log error:', e);
            }

            await db.end();

            return res.status(200).json({ message: 'Password reset successful' });
        }

        await db.end();
        return res.status(400).json({ error: 'Invalid action' });

    } catch (error) {
        console.error('Auth error:', error);
        try {
            await db.end();
        } catch (e) {}
        return res.status(500).json({ error: 'Server error: ' + error.message });
    }
}
