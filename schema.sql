CREATE DATABASE IF NOT EXISTS notes_lab CHARACTER SET utf8mb4;
USE notes_lab;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(254) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(20) NOT NULL DEFAULT 'student'
);

CREATE TABLE notes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  title VARCHAR(120) NOT NULL,
  body TEXT NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Synthetic accounts for local use only. Passwords are bcrypt (password_hash) hashes of the README passwords.
INSERT INTO users (id, email, password_hash, role) VALUES
  (1, 'alex@example.test', '$2y$10$nraoBA/upPsN/U9utT69Tugp9VNtGtNxC5YVAhrzHcp.3Wb.IyWDi', 'student'),
  (2, 'sam@example.test', '$2y$10$QjF1PfFMWwd2fspfvdhx9uKTOT94pWhwRQxU.8eCEfm3Zp0lAmyCm', 'student');

INSERT INTO notes (user_id, title, body) VALUES
  (1, 'Alex: course reminder', 'Review the security reading before class.'),
  (1, 'Alex: project idea', 'Build a small student directory.'),
  (2, 'Sam: study plan', 'Compare authentication and authorization.'),
  (2, 'Sam: meeting', 'Prepare questions for the next group discussion.');
