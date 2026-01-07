# MyDrive - Cloud Storage Platform

A modern cloud storage application built with React and deployed on Vercel.

## Features

- 🔐 User authentication with JWT
- 📁 File upload and management (up to 5MB per file)
- 👥 Admin panel for user management
- 💾 MySQL database integration
- 📊 Google Sheets integration
- ☁️ Google Drive integration
- 📈 Storage quota tracking

## Configuration

### Google Integration

This application integrates with Google services:

- **Google Sheets ID**: `1mtyQ9dssR6cr5WTnlNAR8Q7VOUNy7HO8sadzr7Ynh5Q`
- **Google Drive Folder ID**: `1M-s2_qqLlHw90b57ie4ayww70TTKztth`

### Environment Variables

For production deployment on Vercel, set these environment variables:

```
SPREADSHEET_ID=1mtyQ9dssR6cr5WTnlNAR8Q7VOUNy7HO8sadzr7Ynh5Q
DRIVE_FOLDER_ID=1M-s2_qqLlHw90b57ie4ayww70TTKztth
DB_HOST=sql211.infinityfree.com
DB_PORT=3306
DB_NAME=if0_40677908_astradb1
DB_USER=if0_40677908
DB_PASSWORD=23022Cm032
JWT_SECRET=mydrive_secret_2025
```

## Deployment

### Vercel Deployment

1. Install Vercel CLI: `npm i -g vercel`
2. Login: `vercel login`
3. Deploy: `vercel --prod`

Or use the Vercel dashboard to import this repository.

### Setting Environment Variables on Vercel

1. Go to your project on Vercel
2. Navigate to Settings → Environment Variables
3. Add all variables from `.env.example`
4. Redeploy your project

## Default Admin Credentials

- **User ID**: `admin`
- **Password**: `admin123`

⚠️ **Important**: Change the default admin password after first login!

## Local Development

1. Clone the repository
2. Copy `.env.example` to `.env`
3. Update environment variables if needed
4. Run with `vercel dev` or deploy to Vercel

## Tech Stack

- **Frontend**: React 18, TailwindCSS
- **Backend**: Vercel Serverless Functions (Node.js)
- **Database**: MySQL (InfinityFree)
- **Authentication**: JWT
- **Deployment**: Vercel
- **Integration**: Google Sheets, Google Drive

## Project Structure

```
mydrive/
├── api/
│   ├── auth.js          # Authentication endpoints
│   ├── files.js         # File management endpoints
│   └── admin.js         # Admin panel endpoints
├── index.html           # Main application (React SPA)
├── vercel.json          # Vercel configuration
├── config.js            # Application configuration
├── .env.example         # Environment variables template
└── package.json         # Dependencies
```

## API Endpoints

### Authentication
- `POST /api/auth` - Login and password reset

### Files
- `GET /api/files?action=list` - List user files
- `POST /api/files` - Upload file
- `DELETE /api/files?action=delete&fileId=<id>` - Delete file

### Admin
- `GET /api/admin?action=list-users` - List all users
- `POST /api/admin` - Create new user

## Storage Limits

- **Free Users**: 50 MB
- **Maximum File Size**: 5 MB per file

## License

MIT License - Feel free to use this project for your own purposes.

## Support

For issues or questions, please create an issue in the GitHub repository.

---

**Live Demo**: https://mydrive-ashy.vercel.app