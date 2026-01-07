// Configuration file for MyDrive application

module.exports = {
    // Google Sheets Configuration
    SPREADSHEET_ID: process.env.SPREADSHEET_ID || '1mtyQ9dssR6cr5WTnlNAR8Q7VOUNy7HO8sadzr7Ynh5Q',
    
    // Google Drive Configuration
    DRIVE_FOLDER_ID: process.env.DRIVE_FOLDER_ID || '1M-s2_qqLlHw90b57ie4ayww70TTKztth',
    
    // Database Configuration
    DB_CONFIG: {
        host: process.env.DB_HOST || 'sql211.infinityfree.com',
        port: process.env.DB_PORT || 3306,
        database: process.env.DB_NAME || 'if0_40677908_astradb1',
        user: process.env.DB_USER || 'if0_40677908',
        password: process.env.DB_PASSWORD || '23022Cm032'
    },
    
    // JWT Secret
    JWT_SECRET: process.env.JWT_SECRET || 'mydrive_secret_2025',
    
    // API Base URL
    API_BASE: process.env.API_BASE || '/api'
};