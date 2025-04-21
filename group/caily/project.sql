CREATE TABLE IF NOT EXISTS projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL,
    address TEXT,
    tags TEXT,
    notes TEXT,
    specifications TEXT,
    estimated_hours FLOAT,
    actual_hours FLOAT,
    assignees TEXT,
    responsible_person VARCHAR(100),
    viewable_groups TEXT,
    status VARCHAR(50),
    progress VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Add indexes for better performance
CREATE INDEX idx_projects_code ON projects(code);
CREATE INDEX idx_projects_status ON projects(status);
CREATE INDEX idx_projects_responsible_person ON projects(responsible_person); 