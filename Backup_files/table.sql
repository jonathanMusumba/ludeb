-- Table: Examination_board
CREATE TABLE Examination_board (
    id INT AUTO_INCREMENT PRIMARY KEY,
    Board_name VARCHAR(255) NOT NULL
);

--Table: system_users
CREATE TABLE system_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    Role ENUM('System Admin', 'Data Entrant', 'Examination Admin') NOT NULL,
    Status ENUM('Active', 'Invalid') NOT NULL,
    Created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    last_login DATETIME Default CURRENT_TIMESTAMP
);
-- Table: Sub_counties
CREATE TABLE Sub_counties (
    id INT AUTO_INCREMENT PRIMARY KEY,
    SubCounty VARCHAR(255) NOT NULL
);
-- Table: School_Types
CREATE TABLE School_Types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('Government', 'Private') NOT NULL
);
-- Table: Schools
CREATE TABLE Schools (
    id INT AUTO_INCREMENT PRIMARY KEY,
    CenterNo INT,
    School_Name VARCHAR(255) NOT NULL,
    Sub_county INT,
    School_type INT,
    Status ENUM('Active', 'Not Active') NOT NULL,
    ResultsStatus ENUM('Declared', 'Not Declared', 'Partially Declared') DEFAULT 'Not Declared',
    FOREIGN KEY (Sub_county) REFERENCES Sub_counties(id),
    FOREIGN KEY (School_type) REFERENCES School_Types(id)
);

-- Table: Exam_years
CREATE TABLE Exam_years (
    id INT AUTO_INCREMENT PRIMARY KEY,
    Year INT NOT NULL
);

-- Table: Candidates
CREATE TABLE Candidates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    IndexNo VARCHAR(10) UNIQUE,
    Candidate_Name VARCHAR(255) NOT NULL,
    Gender ENUM('M', 'F') NOT NULL,
    School_Id INT,
    Exam_Year INT,
    Sat ENUM('Yes', 'No') DEFAULT 'No',
    Marks ENUM('No', 'Partial', 'Full') DEFAULT 'No',
    FOREIGN KEY (School_Id) REFERENCES Schools(CenterNo),
    FOREIGN KEY (Exam_Year) REFERENCES Exam_years(id)
);

-- Table: Subjects
CREATE TABLE Subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    Name VARCHAR(255) NOT NULL,
    Code VARCHAR(50) NOT NULL
);

-- Table: Marks
CREATE TABLE Marks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    candidate_id INT,
    subject_id INT,
    mark DECIMAL(5, 2),
    submitted_at DATETIME,
    submitted_by INT,
    school_id INT,
    updated_at DATETIME,
    edited_by INT,
    FOREIGN KEY (candidate_id) REFERENCES Candidates(IndexNo),
    FOREIGN KEY (subject_id) REFERENCES Subjects(id),
    FOREIGN KEY (school_id) REFERENCES Schools(id),
    FOREIGN KEY (submitted_by) REFERENCES system_users(id),
    FOREIGN KEY (edited_by) REFERENCES system_users(id)
);
-- Table: Grading
CREATE TABLE Grading (
    id INT AUTO_INCREMENT PRIMARY KEY,
    Range_from DECIMAL(5, 2) NOT NULL,
    Range_to DECIMAL(5, 2) NOT NULL,
    Grade VARCHAR(2) NOT NULL,
    Score INT NOT NULL
);
-- Table: Results
CREATE TABLE Results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    candidate_id INT NOT NULL,
    subject_id INT NOT NULL,
    mark DECIMAL(5, 2) NOT NULL,
    score INT NOT NULL,
    processed_at DATETIME NOT NULL,
    updated_at DATETIME,
    processed_by INT NOT NULL,
    updated_by INT,
    school_id INT NOT NULL,
    aggregates INT NOT NULL,
    division VARCHAR(10) NOT NULL,
    FOREIGN KEY (candidate_id) REFERENCES Candidates(IndexNo),
    FOREIGN KEY (subject_id) REFERENCES Subjects(id),
    FOREIGN KEY (school_id) REFERENCES Schools(id),
    FOREIGN KEY (processed_by) REFERENCES system_users(id),
    FOREIGN KEY (updated_by) REFERENCES system_users(id)
);

