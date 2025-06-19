# Bulk Email Sender

A PHP application for sending personalized bulk emails with support for Excel-based recipient lists and HTML email templates.

## Features

- 📧 Send personalized bulk emails
- 📊 Upload recipient data via Excel (XLSX) files
- ✨ WYSIWYG email editor with rich text formatting
- 🔍 Email preview before sending
- 🔒 CSRF protection
- 🔐 Secure SMTP configuration
- 📱 Responsive design

## Requirements

- PHP 8.0 or higher
- Composer
- Web server (Apache/Nginx)
- SMTP server access (e.g., Gmail)

## Installation

1. Clone the repository:
```bash
git clone <repository-url>
cd bulk-mail
```

2. Install dependencies:
```bash
composer install
```

3. Create environment file:
```bash
cp .env.example .env
```

4. Configure your environment:
   - Edit `.env` file with your SMTP settings
   - For Gmail, you'll need to:
     - Enable 2-Step Verification
     - Generate an App Password
     - Use the App Password in SMTP_PASSWORD

## Configuration

### SMTP Settings
Edit your `.env` file with the following settings:

```env
# SMTP Configuration
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=your-email@gmail.com
SMTP_PASSWORD=your-app-password
SMTP_ENCRYPTION=tls

# Application Configuration
APP_ENV=dev
APP_SECRET=your-secret-key-here

# Security Configuration
ALLOWED_IPS=192.168.1.100,10.0.0.0/8,172.16.0.0/12
```

### IP Whitelist Configuration
The `ALLOWED_IPS` setting supports multiple formats:
- Single IP: `192.168.1.100`
- CIDR notation: `192.168.1.0/24`
- Wildcard notation: `192.168.*.*`
- Multiple IPs separated by commas: `192.168.1.100,10.0.0.0/8`

Examples:
```env
# Allow specific IPs
ALLOWED_IPS=192.168.1.100,192.168.1.101

# Allow IP ranges
ALLOWED_IPS=192.168.1.0/24,10.0.0.0/8

# Allow all (empty or not set)
ALLOWED_IPS=
```

### Excel File Format
Your Excel file should have:
- First row as headers
- Required column: `email`
- Additional columns can be used as variables in email templates

Example Excel format:
| email | name | company |
|-------|------|---------|
| john@example.com | John Doe | Company A |
| jane@example.com | Jane Smith | Company B |

## Usage

1. Access the application through your web server
2. Enter email subject
3. Create email content using the WYSIWYG editor
   - Use variables like `{name}` or `{company}` for personalization
4. Upload Excel file with recipient data
5. Click "Preview" to see how emails will look for each recipient
6. Click "Send" to deliver the emails

## Security

- CSRF protection is enabled by default
- SMTP credentials are stored in `.env` file (not in version control)
- Input validation and sanitization
- Secure password handling

## Development

### Project Structure
```
bulk-mail/
├── src/
│   ├── Config/
│   ├── Controller/
│   └── Service/
├── templates/
├── vendor/
├── .env
├── .env.example
├── .gitignore
├── composer.json
└── index.php
```

### Adding New Features
1. Create new service classes in `src/Service/`
2. Add controllers in `src/Controller/`
3. Update templates in `templates/`

## Troubleshooting

### Common Issues

1. **SMTP Connection Failed**
   - Verify SMTP credentials in `.env`
   - Check if 2-Step Verification is enabled (for Gmail)
   - Ensure correct App Password is used

2. **Excel Upload Issues**
   - Verify file format is XLSX
   - Check if required 'email' column exists
   - Ensure first row contains headers

3. **CSRF Token Invalid**
   - Clear browser cache
   - Ensure cookies are enabled
   - Check if session is working

## License

This project is licensed under the MIT License.

## Contributing

1. Fork the repository
2. Create your feature branch
3. Commit your changes
4. Push to the branch
5. Create a new Pull Request 