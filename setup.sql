-- Patient Management System Database Setup
-- Created: December 2025

-- Create database
CREATE DATABASE IF NOT EXISTS patient_management;
USE patient_management;

-- Users table for authentication (doctors, nurses, admins)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'doctor') DEFAULT 'doctor',
    phone VARCHAR(15),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE
);

-- Patients table
CREATE TABLE patients (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id VARCHAR(20) UNIQUE NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    date_of_birth DATE NOT NULL,
    gender ENUM('Male', 'Female', 'Other') NOT NULL,
    phone VARCHAR(15) NOT NULL,
    email VARCHAR(100),
    address TEXT NOT NULL,
    emergency_contact_name VARCHAR(100),
    emergency_contact_phone VARCHAR(15),
    blood_type VARCHAR(5),
    allergies TEXT,
    insurance_number VARCHAR(50),
    insurance_provider VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE doctors (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE,
    doctor_id VARCHAR(20) UNIQUE NOT NULL,
    specialization VARCHAR(100) NOT NULL,
    license_number VARCHAR(50) UNIQUE NOT NULL,
    department VARCHAR(100),
    consultation_fee DECIMAL(10,2) DEFAULT 0.00,
    available_days VARCHAR(50), -- JSON format: ["Monday", "Tuesday", ...]
    available_time_start TIME,
    available_time_end TIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Appointments table
CREATE TABLE appointments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    appointment_id VARCHAR(20) UNIQUE NOT NULL,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    status ENUM('scheduled', 'confirmed', 'completed', 'cancelled', 'no-show') DEFAULT 'scheduled',
    reason TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Medical history table
CREATE TABLE medical_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    visit_date DATE NOT NULL,
    chief_complaint TEXT,
    diagnosis TEXT,
    treatment TEXT,
    prescribed_medications TEXT,
    follow_up_date DATE,
    vital_signs JSON, -- {"blood_pressure": "120/80", "temperature": "98.6", "heart_rate": "72", "weight": "70kg"}
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id)
);

-- Prescriptions table
CREATE TABLE prescriptions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    prescription_id VARCHAR(20) UNIQUE NOT NULL,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    medical_history_id INT,
    medication_name VARCHAR(100) NOT NULL,
    dosage VARCHAR(50) NOT NULL,
    frequency VARCHAR(50) NOT NULL,
    duration VARCHAR(50) NOT NULL,
    instructions TEXT,
    prescribed_date DATE NOT NULL,
    status ENUM('active', 'completed', 'discontinued') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id),
    FOREIGN KEY (medical_history_id) REFERENCES medical_history(id)
);



-- Insert default admin user
INSERT INTO users (username, email, password, full_name, role, phone) VALUES
('admin', 'admin@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin', '+1234567890');



CREATE INDEX idx_patients_patient_id ON patients(patient_id);
CREATE INDEX idx_appointments_date ON appointments(appointment_date);
CREATE INDEX idx_appointments_status ON appointments(status);
CREATE INDEX idx_medical_history_patient ON medical_history(patient_id);
CREATE INDEX idx_medical_history_date ON medical_history(visit_date);
CREATE INDEX idx_prescriptions_patient ON prescriptions(patient_id);

-- Create triggers for automatic ID generation
DELIMITER //

CREATE TRIGGER before_patient_insert 
BEFORE INSERT ON patients
FOR EACH ROW
BEGIN
    IF NEW.patient_id = '' OR NEW.patient_id IS NULL THEN
        SET NEW.patient_id = CONCAT('PAT', LPAD((SELECT IFNULL(MAX(CAST(SUBSTRING(patient_id, 4) AS UNSIGNED)), 0) + 1 FROM patients), 6, '0'));
    END IF;
END//

CREATE TRIGGER before_appointment_insert 
BEFORE INSERT ON appointments
FOR EACH ROW
BEGIN
    IF NEW.appointment_id = '' OR NEW.appointment_id IS NULL THEN
        SET NEW.appointment_id = CONCAT('APT', LPAD((SELECT IFNULL(MAX(CAST(SUBSTRING(appointment_id, 4) AS UNSIGNED)), 0) + 1 FROM appointments), 6, '0'));
    END IF;
END//

CREATE TRIGGER before_prescription_insert 
BEFORE INSERT ON prescriptions
FOR EACH ROW
BEGIN
    IF NEW.prescription_id = '' OR NEW.prescription_id IS NULL THEN
        SET NEW.prescription_id = CONCAT('PRE', LPAD((SELECT IFNULL(MAX(CAST(SUBSTRING(prescription_id, 4) AS UNSIGNED)), 0) + 1 FROM prescriptions), 6, '0'));
    END IF;
END//



DELIMITER ;

-- Note: Default password for all users is 'password'
-- Remember to change these passwords in production!