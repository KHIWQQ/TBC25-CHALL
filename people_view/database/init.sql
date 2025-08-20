-- Database initialization script
USE hr_portal;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(500) UNIQUE NOT NULL,
    email VARCHAR(500) UNIQUE NOT NULL,
    password VARCHAR(500) NOT NULL,
    full_name VARCHAR(500) NOT NULL,
    department VARCHAR(500),
    position VARCHAR(500),
    hire_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(500) NOT NULL,
    description TEXT,
    file_path VARCHAR(500) NOT NULL,
    category VARCHAR(500),
    uploaded_by INT,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS benefits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    benefit_type VARCHAR(500) NOT NULL,
    description TEXT,
    status ENUM('active', 'pending', 'expired') DEFAULT 'active',
    start_date DATE,
    end_date DATE,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS performance_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    reviewer_id INT,
    review_period VARCHAR(500),
    overall_rating DECIMAL(3,2),
    goals TEXT,
    achievements TEXT,
    areas_for_improvement TEXT,
    comments TEXT,
    review_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (reviewer_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS job_postings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(500) NOT NULL,
    department VARCHAR(500) NOT NULL,
    location VARCHAR(500),
    job_type ENUM('full-time', 'part-time', 'contract', 'internship') DEFAULT 'full-time',
    salary_range VARCHAR(500),
    description TEXT NOT NULL,
    requirements TEXT,
    responsibilities TEXT,
    benefits TEXT,
    status ENUM('active', 'closed', 'draft') DEFAULT 'active',
    posted_by INT,
    posted_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    closing_date DATE,
    FOREIGN KEY (posted_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS job_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    applicant_id INT NOT NULL,
    cover_letter TEXT,
    resume_path VARCHAR(500),
    status ENUM('pending', 'reviewed', 'shortlisted', 'rejected', 'hired') DEFAULT 'pending',
    applied_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_by INT,
    review_date TIMESTAMP NULL,
    notes TEXT,
    FOREIGN KEY (job_id) REFERENCES job_postings(id) ON DELETE CASCADE,
    FOREIGN KEY (applicant_id) REFERENCES users(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id),
    UNIQUE KEY unique_application (job_id, applicant_id)
);

-- Insert sample data
INSERT IGNORE INTO users (username, email, password, full_name, department, position, hire_date) VALUES
('admin', 'admin@company.com', '$2y$10$LX8gpttn0p/SUT0n0Fi6RO5aYAyiAT9l0JiodDQmHCUAV6XAg0tKe', 'Admin User', 'HR', 'HR Manager', '2020-01-15');

INSERT IGNORE INTO documents (title, description, file_path, category, uploaded_by) VALUES
('Employee Handbook', 'Complete guide for all employees', '/uploads/employee_handbook.pdf', 'Policy', 1),
('Code of Conduct', 'Company code of conduct and ethics', '/uploads/code_of_conduct.pdf', 'Policy', 1),
('Benefits Guide', 'Overview of company benefits', '/uploads/benefits_guide.pdf', 'Benefits', 1);

INSERT IGNORE INTO benefits (user_id, benefit_type, description, status, start_date, end_date) VALUES
(2, 'Health Insurance', 'Comprehensive health coverage', 'active', '2021-03-10', '2024-03-10'),
(2, 'Dental Insurance', 'Dental coverage plan', 'active', '2021-03-10', '2024-03-10'),
(3, 'Health Insurance', 'Comprehensive health coverage', 'active', '2021-06-20', '2024-06-20');

INSERT IGNORE INTO performance_reviews (user_id, reviewer_id, review_period, overall_rating, goals, achievements, areas_for_improvement, comments) VALUES
(2, 1, 'Q1 2024', 4.2, 'Complete project X, Learn new framework', 'Successfully delivered 3 major features', 'Time management, Communication', 'Great performance overall'),
(3, 1, 'Q1 2024', 4.5, 'Increase campaign ROI, Lead team project', 'Achieved 25% increase in engagement', 'Strategic planning', 'Excellent work and leadership');

-- Sample job postings
INSERT IGNORE INTO job_postings (title, department, location, job_type, salary_range, description, requirements, responsibilities, benefits, posted_by, closing_date) VALUES
('Senior Software Developer', 'Engineering', 'New York, NY', 'full-time', '$80,000 - $120,000', 'We are looking for an experienced software developer to join our growing engineering team.', '5+ years experience, PHP/MySQL, JavaScript, Git', 'Develop new features, Code review, Mentor junior developers', 'Health insurance, 401k, Flexible PTO', 1, '2024-12-31'),
('Marketing Specialist', 'Marketing', 'Remote', 'full-time', '$50,000 - $70,000', 'Join our marketing team to help grow our brand and reach new customers.', '3+ years experience, Social media marketing, Content creation', 'Manage social media, Create content, Analyze campaigns', 'Health insurance, Remote work, Professional development', 1, '2024-12-31'),
('HR Intern', 'Human Resources', 'Chicago, IL', 'internship', '$20/hour', 'Gain valuable experience in HR operations and recruitment.', 'Currently enrolled in HR or related field', 'Assist with recruitment, Maintain records, Support HR team', 'Mentorship, Networking opportunities, Potential full-time offer', 1, '2024-12-31');