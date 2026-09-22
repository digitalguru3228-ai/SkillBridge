USE industry_hub;
CREATE TABLE IF NOT EXISTS users (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(150) NOT NULL,
 email VARCHAR(190) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 role ENUM('industry','institute','admin') NOT NULL,
 company_id INT UNSIGNED NULL,
 institute_id INT UNSIGNED NULL,
 status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_users_role(role), INDEX idx_users_company(company_id), INDEX idx_users_institute(institute_id),
 CONSTRAINT fk_users_company FOREIGN KEY(company_id) REFERENCES companies(id) ON DELETE SET NULL,
 CONSTRAINT fk_users_institute FOREIGN KEY(institute_id) REFERENCES institutes(id) ON DELETE SET NULL
) ENGINE=InnoDB;
