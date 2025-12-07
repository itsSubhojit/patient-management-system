# Patient Management System

A comprehensive web-based patient management system designed for hospitals, clinics, and medical practices. Built with PHP, MySQL, and Bootstrap 5.

## Features

### Core Functionality
- **User Authentication**: Secure login system with role-based access control (no public registration)
- **Patient Management**: Add, edit, and view patient details
- **Appointment Scheduling**: Book, manage, and track appointments
- **Dashboard**: Overview of key metrics and recent activities

### User Roles
- **Admin**: Full system access, user management, system settings
- **Doctor**: Patient records, appointments, diagnosis

### Key Features
- Responsive design for mobile and desktop
- Patient ID auto-generation
- Appointment status management
- Role-based navigation and permissions
- Data validation and security measures

## Installation

### Prerequisites
- XAMPP or similar local server environment
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Modern web browser

### Setup Instructions

1. **Install XAMPP**
   - Download and install XAMPP from https://www.apachefriends.org/
   - Start Apache and MySQL services

2. **Database Setup**
   - Open phpMyAdmin (http://localhost/phpmyadmin)
   - Import the `setup.sql` file to create the database and tables
   - Or run the SQL commands manually from the setup.sql file

3. **File Placement**
   - Copy the `patient-management-system` folder to `C:\xampp\htdocs\`
   - Ensure proper file permissions

4. **Configuration**
   - Open `includes/config.php`
   - Verify database connection settings:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_USER', 'root');
     define('DB_PASS', '');
     define('DB_NAME', 'patient_management');
     ```

5. **Access the System**
   - Open your browser and go to: `http://localhost/patient-management/`
   - You'll be redirected to the login page

## Default Login Credentials

### Administrator
- **Username**: `admin`
- **Password**: `password`

### Doctor
- **Username**: `dr.smith`
- **Password**: `password`

**⚠️ Important**: Change these default passwords after first login!

## System Requirements

### Server Requirements
- PHP 7.4+
- MySQL 5.7+
- Apache/Nginx web server
- PDO MySQL extension
- JSON extension

### Browser Support
- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

## File Structure

```
patient-management-system/
├── css/
│   └── style.css
├── js/
│   └── dashboard.js
├── images/
│   └── back-img.jpg
├── includes/
│   ├── config.php
│   └── navbar.php
├── dashboard.php
├── login.php
├── logout.php
├── patients.php
├── appointments.php
├── index.php
└── setup.sql
```

## Usage Guide

### Getting Started
1. Login with default credentials
2. Change default passwords
3. Add doctors (admin only)
4. Register patients
5. Schedule appointments

### Patient Management
- Click "Patients" in the navigation bar
- Use "Add New Patient" to register patients
- Search and edit patient information
- View patient details and appointment history

### Appointment Scheduling
- Go to "Appointments" in the navigation bar
- Click "Schedule Appointment"
- Select patient and doctor
- Choose date and time
- Add reason for visit
- Manage appointment status

### User Management (Admin Only)
- Add new doctors
- Manage user permissions

## Database Schema

### Main Tables

- **users**: System users and authentication
- **patients**: Patient information and demographics
- **doctors**: Doctor profiles and specializations
- **appointments**: Appointment scheduling and management

### Key Relationships

- Users can be linked to doctor profiles
- Appointments link patients with doctors

## Security Features

- Password hashing using PHP's `password_hash()`
- SQL injection prevention with prepared statements
- Input sanitization and validation
- Session-based authentication
- Role-based access control
- CSRF protection measures

## Customization

### Adding New Features
1. Create new PHP files following the existing structure
2. Include proper authentication checks
3. Use the established CSS classes for styling
4. Add navigation links in navbar.php
5. Update database schema if needed

### Styling Changes
- Modify `css/style.css` for visual customizations
- Use Bootstrap 5 classes for responsive design
- Custom CSS variables for color scheme changes

### Database Modifications
- Add new tables following the naming convention
- Include proper foreign key relationships
- Create appropriate indexes for performance
- Update triggers for auto-ID generation

## Troubleshooting

### Common Issues

**Database Connection Failed**
- Check XAMPP MySQL service is running
- Verify database credentials in config.php
- Ensure database exists and tables are created

**Login Issues**
- Verify default credentials
- Check if user exists in database
- Clear browser cache and cookies

**Permission Errors**
- Check file permissions in htdocs folder
- Verify Apache has read/write access
- Check PHP error logs for details

**Styling Issues**
- Verify CSS and JS files are loading
- Check browser developer tools for errors
- Ensure Bootstrap CDN links are accessible

### Getting Help
1. Check PHP error logs in XAMPP
2. Use browser developer tools
3. Verify database connections
4. Review file permissions

## Contributing

### Development Guidelines
- Follow PSR coding standards
- Use meaningful variable names
- Comment complex logic
- Test all functionality
- Maintain responsive design

### Future Enhancements
- Email notifications
- SMS integration
- Advanced reporting
- API development
- Mobile app support

## License

This project is developed for educational and demonstration purposes. Please ensure compliance with healthcare regulations (HIPAA, GDPR, etc.) before using in production environments.

## Support

For technical support or questions:
- Review the troubleshooting section
- Check PHP and MySQL error logs
- Verify system requirements
- Test with default credentials

---

**Note**: This system is designed for demonstration purposes. For production use in healthcare environments, additional security measures, compliance features, and professional testing are required.