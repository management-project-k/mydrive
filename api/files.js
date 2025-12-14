// api/files.js - File Management Serverless Function
const mysql = require('serverless-mysql');
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
    res.setHeader('Access-Control-Allow-Methods', 'GET, POST, DELETE, OPTIONS');
    res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');

    if (req.method === 'OPTIONS') {
        return res.status(200).end();
    }

    const userId = await getUserFromToken(req);
    if (!userId) {
        return res.status(401).json({ error: 'Unauthorized' });
    }

    try {
        // ====================================================================
        // LIST FILES
        // ====================================================================
        if (req.method === 'GET' && req.query.action === 'list') {
            const files = await db.query(
                'SELECT file_id, file_name, file_size, file_type, cloudinary_url, uploaded_at FROM files WHERE user_id = ? ORDER BY uploaded_at DESC',
                [userId]
            );

            await db.end();
            return res.status(200).json({ files, folders: [] });
        }

        // ====================================================================
        // UPLOAD FILE
        // ====================================================================
        if (req.method === 'POST' && req.body.action === 'upload') {
            const { file, fileName, fileSize } = req.body;

            if (fileSize > 5 * 1024 * 1024) {
                await db.end();
                return res.status(400).json({ error: 'File size exceeds 5MB limit' });
            }

            // Check storage quota
            const users = await db.query(
                'SELECT storage_used, storage_limit FROM users WHERE user_id = ?',
                [userId]
            );
            const user = users[0];

            if (user.storage_used + fileSize > user.storage_limit) {
                await db.end();
                return res.status(403).json({ error: 'Storage quota exceeded' });
            }

            // Generate file ID
            const fileId = require('crypto').randomBytes(16).toString('hex');

            // Insert file
            await db.query(
                'INSERT INTO files (file_id, user_id, cloudinary_id, file_name, file_size, file_type, cloudinary_url) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [fileId, userId, fileId, fileName, fileSize, 'unknown', file]
            );

            // Update storage
            await db.query(
                'UPDATE users SET storage_used = storage_used + ? WHERE user_id = ?',
                [fileSize, userId]
            );

            // Log activity
            await db.query(
                'INSERT INTO activity_logs (user_id, action, details) VALUES (?, ?, ?)',
                [userId, 'FILE_UPLOAD', `Uploaded ${fileName}`]
            );

            await db.end();
            return res.status(200).json({
                fileId,
                fileName,
                fileUrl: file,
                message: 'File uploaded successfully'
            });
        }

        // ====================================================================
        // DELETE FILE
        // ====================================================================
        if (req.method === 'DELETE' && req.query.action === 'delete') {
            const { fileId } = req.query;

            const files = await db.query(
                'SELECT * FROM files WHERE file_id = ? AND user_id = ?',
                [fileId, userId]
            );
            const file = files[0];

            if (!file) {
                await db.end();
                return res.status(404).json({ error: 'File not found' });
            }

            await db.query('DELETE FROM files WHERE file_id = ?', [fileId]);

            await db.query(
                'UPDATE users SET storage_used = storage_used - ? WHERE user_id = ?',
                [file.file_size, userId]
            );

            await db.query(
                'INSERT INTO activity_logs (user_id, action, details) VALUES (?, ?, ?)',
                [userId, 'FILE_DELETE', `Deleted ${file.file_name}`]
            );

            await db.end();
            return res.status(200).json({ message: 'File deleted successfully' });
        }

        await db.end();
        return res.status(400).json({ error: 'Invalid request' });

    } catch (error) {
        console.error('Files error:', error);
        await db.end();
        return res.status(500).json({ error: 'Server error' });
    }
};
