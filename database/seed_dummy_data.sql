
-- ============================================
-- School Management System - Dummy Data Seed
-- Creates: 12 Students, 5 Lecturers, 2 HODs
-- Plus: 3 Departments, 12 Courses, Enrollments,
--       Grades, Attendance, Payments, Schedules
-- ============================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
USE school_management;

SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE attendance;
TRUNCATE TABLE grades;
TRUNCATE TABLE course_enrollments;
TRUNCATE TABLE course_approvals;
TRUNCATE TABLE payments;
TRUNCATE TABLE course_schedules;
TRUNCATE TABLE courses;
DELETE FROM departments WHERE id > 0;
DELETE FROM users WHERE id > 0;
SET FOREIGN_KEY_CHECKS = 1;

ALTER TABLE users AUTO_INCREMENT = 1;
ALTER TABLE departments AUTO_INCREMENT = 1;
ALTER TABLE courses AUTO_INCREMENT = 1;
ALTER TABLE course_enrollments AUTO_INCREMENT = 1;
ALTER TABLE grades AUTO_INCREMENT = 1;
ALTER TABLE attendance AUTO_INCREMENT = 1;
ALTER TABLE payments AUTO_INCREMENT = 1;
ALTER TABLE course_schedules AUTO_INCREMENT = 1;
ALTER TABLE course_approvals AUTO_INCREMENT = 1;

SET @admin_hash = '$2y$10$7FDU4tIcQ0leEF3YGn4EjeJ.6JaD72mZ5E8rcTNxaYooV9zNC0gvy';

INSERT INTO users (id, email, password_hash, first_name, last_name, role_id, is_active)
VALUES (1, 'admin@school.edu', @admin_hash, 'Admin', 'User', 1, 1);

INSERT INTO departments (id, name, code, description) VALUES
(1, 'Computer Science', 'CSC', 'Department of Computer Science and Information Technology'),
(2, 'Business Administration', 'BUS', 'Department of Business Administration and Management'),
(3, 'Electrical Engineering', 'EEE', 'Department of Electrical and Electronics Engineering');

INSERT INTO users (id, email, password_hash, first_name, last_name, phone, department_id, role_id, is_active, date_of_birth, gender, address, national_id, emergency_contact, emergency_contact_name) VALUES
(2, 'mugabo@school.edu', @admin_hash, 'Jean-Pierre', 'Mugabo', '+250788100001', 1, 2, 1, '1978-03-15', 'Male', 'KG 7 Ave, Kigali', '1199880011223', '+250788100991', 'Marie Mugabo'),
(3, 'uwimana@school.edu', @admin_hash, 'Alice', 'Uwimana', '+250788100002', 2, 2, 1, '1982-07-22', 'Female', 'KN 5 Rd, Kigali', '1199880011224', '+250788100992', 'Paul Uwimana');

UPDATE departments SET hod_id = 2 WHERE id = 1;
UPDATE departments SET hod_id = 3 WHERE id = 2;

INSERT INTO users (id, email, password_hash, first_name, last_name, phone, department_id, role_id, is_active, date_of_birth, gender, address, national_id, emergency_contact, emergency_contact_name) VALUES
(4, 'ndayisaba@school.edu', @admin_hash, 'Emmanuel', 'Ndayisaba', '+250788200001', 1, 3, 1, '1985-01-10', 'Male', 'KG 12 Ave, Kigali', '1199880022001', '+250788200991', 'Grace Ndayisaba'),
(5, 'ingabire@school.edu', @admin_hash, 'Chantal', 'Ingabire', '+250788200002', 1, 3, 1, '1988-06-18', 'Female', 'KN 3 Rd, Kigali', '1199880022002', '+250788200992', 'Jean Ingabire'),
(6, 'habimana@school.edu', @admin_hash, 'Patrick', 'Habimana', '+250788200003', 1, 3, 1, '1983-11-25', 'Male', 'KG 7 Ave, Kigali', '1199880022003', '+250788200993', 'Diane Habimana'),
(7, 'kamana@school.edu', @admin_hash, 'Isabelle', 'Kamana', '+250788200004', 2, 3, 1, '1990-04-05', 'Female', 'KN 8 Rd, Kigali', '1199880022004', '+250788200994', 'Eric Kamana'),
(8, 'mukamana@school.edu', @admin_hash, 'Dieudonne', 'Mukamana', '+250788200005', 3, 3, 1, '1987-09-12', 'Male', 'KG 15 Ave, Kigali', '1199880022005', '+250788200995', 'Solange Mukamana');

INSERT INTO users (id, email, password_hash, first_name, last_name, phone, department_id, role_id, is_active, reg_number, date_of_birth, gender, address, national_id, emergency_contact, emergency_contact_name) VALUES
(9, 'uwera@school.edu', @admin_hash, 'Kevin', 'Uwera', '+250788300001', 1, 4, 1, 'CSC/2025/001', '2002-03-14', 'Male', 'KG 5 Ave, Kigali', '1199880033001', '+250788300991', 'Jean Uwera'),
(10, 'mutoni@school.edu', @admin_hash, 'Sandrine', 'Mutoni', '+250788300002', 1, 4, 1, 'CSC/2025/002', '2001-08-21', 'Female', 'KN 2 Rd, Kigali', '1199880033002', '+250788300992', 'Robert Mutoni'),
(11, 'nzeyimana@school.edu', @admin_hash, 'Eric', 'Nzeyimana', '+250788300003', 1, 4, 1, 'CSC/2025/003', '2003-01-30', 'Male', 'KG 9 Ave, Kigali', '1199880033003', '+250788300993', 'Alice Nzeyimana'),
(12, 'iradukunda@school.edu', @admin_hash, 'Belyse', 'Iradukunda', '+250788300004', 1, 4, 1, 'CSC/2025/004', '2002-05-17', 'Female', 'KN 6 Rd, Kigali', '1199880033004', '+250788300994', 'Patrick Iradukunda'),
(13, 'gakuru@school.edu', @admin_hash, 'Olivier', 'Gakuru', '+250788300005', 1, 4, 1, 'CSC/2025/005', '2001-12-03', 'Male', 'KG 11 Ave, Kigali', '1199880033005', '+250788300995', 'Marie Gakuru'),
(14, 'nyirahabimana@school.edu', @admin_hash, 'Immaculee', 'Nyirahabimana', '+250788300006', 1, 4, 1, 'CSC/2025/006', '2003-04-22', 'Female', 'KN 4 Rd, Kigali', '1199880033006', '+250788300996', 'Jean Nyirahabimana'),
(15, 'twizeyimana@school.edu', @admin_hash, 'Aimable', 'Twizeyimana', '+250788300007', 1, 4, 1, 'CSC/2025/007', '2002-09-08', 'Male', 'KG 14 Ave, Kigali', '1199880033007', '+250788300997', 'Claudine Twizeyimana'),
(16, 'umutoni@school.edu', @admin_hash, 'Yvette', 'Umutoni', '+250788300008', 1, 4, 1, 'CSC/2025/008', '2001-07-15', 'Female', 'KN 1 Rd, Kigali', '1199880033008', '+250788300998', 'Augustin Umutoni'),
(17, 'nkurunziza@school.edu', @admin_hash, 'David', 'Nkurunziza', '+250788300009', 2, 4, 1, 'BUS/2025/001', '2002-11-20', 'Male', 'KN 10 Rd, Kigali', '1199880033009', '+250788300999', 'Esther Nkurunziza'),
(18, 'mukamutara@school.edu', @admin_hash, 'Ange', 'Mukamutara', '+250788300010', 2, 4, 1, 'BUS/2025/002', '2003-02-14', 'Female', 'KG 8 Ave, Kigali', '1199880033010', '+250788300910', 'Jean Mukamutara'),
(19, 'ibirabose@school.edu', @admin_hash, 'Jean', 'Ibirabose', '+250788300011', 3, 4, 1, 'EEE/2025/001', '2002-06-28', 'Male', 'KN 7 Rd, Kigali', '1199880033011', '+250788300911', 'Agnes Ibirabose'),
(20, 'umwali@school.edu', @admin_hash, 'Josiane', 'Umwali', '+250788300012', 3, 4, 1, 'EEE/2025/002', '2001-10-05', 'Female', 'KG 13 Ave, Kigali', '1199880033012', '+250788300912', 'Emmanuel Umwali');

INSERT INTO courses (id, code, name, description, credits, semester, department_id, lecturer_id, academic_year) VALUES
(1, 'CSC101', 'Introduction to Programming', 'Fundamentals of programming using Python', 3, 1, 1, 4, '2025-2026'),
(2, 'CSC102', 'Data Structures and Algorithms', 'Arrays, linked lists, trees, graphs, sorting', 3, 1, 1, 4, '2025-2026'),
(3, 'CSC103', 'Discrete Mathematics', 'Logic, sets, relations, combinatorics', 3, 1, 1, 5, '2025-2026'),
(4, 'CSC201', 'Database Systems', 'Relational databases, SQL, normalization', 3, 2, 1, 5, '2025-2026'),
(5, 'CSC202', 'Operating Systems', 'Process management, memory, file systems', 3, 2, 1, 6, '2025-2026'),
(6, 'CSC203', 'Computer Networks', 'OSI model, TCP/IP, networking protocols', 3, 2, 1, 6, '2025-2026'),
(7, 'BUS101', 'Principles of Management', 'Management theories and organizational behavior', 3, 1, 2, 7, '2025-2026'),
(8, 'BUS102', 'Financial Accounting', 'Double-entry bookkeeping, financial statements', 3, 1, 2, 7, '2025-2026'),
(9, 'BUS201', 'Marketing Management', 'Market research, segmentation, marketing mix', 3, 2, 2, 7, '2025-2026'),
(10, 'BUS202', 'Human Resource Management', 'Recruitment, training, performance management', 3, 2, 2, 7, '2025-2026'),
(11, 'EEE101', 'Circuit Theory', 'Kirchhoff laws, network analysis, AC circuits', 3, 1, 3, 8, '2025-2026'),
(12, 'EEE201', 'Digital Electronics', 'Logic gates, flip-flops, counters, registers', 3, 2, 3, 8, '2025-2026');

INSERT INTO course_schedules (course_id, day, start_time, end_time, location) VALUES
(1, 'Monday', '08:00:00', '10:00:00', 'Lab 101 - CS Building'),
(2, 'Tuesday', '08:00:00', '10:00:00', 'Lecture Hall A'),
(3, 'Wednesday', '08:00:00', '10:00:00', 'Lecture Hall B'),
(4, 'Monday', '10:00:00', '12:00:00', 'Lab 102 - CS Building'),
(5, 'Thursday', '08:00:00', '10:00:00', 'Lecture Hall A'),
(6, 'Friday', '08:00:00', '10:00:00', 'Lecture Hall C'),
(7, 'Tuesday', '10:00:00', '12:00:00', 'Lecture Hall D'),
(8, 'Wednesday', '10:00:00', '12:00:00', 'Room 201 - Business Block'),
(9, 'Thursday', '10:00:00', '12:00:00', 'Room 202 - Business Block'),
(10, 'Friday', '10:00:00', '12:00:00', 'Room 201 - Business Block'),
(11, 'Monday', '14:00:00', '16:00:00', 'Lab 201 - Engineering Block'),
(12, 'Wednesday', '14:00:00', '16:00:00', 'Lab 202 - Engineering Block');

INSERT INTO course_enrollments (student_id, course_id, status) VALUES
(9, 1, 'enrolled'), (9, 2, 'enrolled'), (9, 3, 'enrolled'), (9, 4, 'enrolled'), (9, 5, 'enrolled'), (9, 6, 'enrolled'),
(10, 1, 'enrolled'), (10, 2, 'enrolled'), (10, 3, 'enrolled'), (10, 4, 'enrolled'), (10, 5, 'enrolled'),
(11, 1, 'enrolled'), (11, 2, 'enrolled'), (11, 3, 'enrolled'), (11, 4, 'enrolled'),
(12, 1, 'enrolled'), (12, 2, 'enrolled'), (12, 3, 'enrolled'),
(13, 1, 'enrolled'), (13, 2, 'enrolled'), (13, 3, 'enrolled'), (13, 4, 'enrolled'),
(14, 1, 'enrolled'), (14, 2, 'enrolled'), (14, 3, 'enrolled'), (14, 4, 'enrolled'), (14, 5, 'enrolled'),
(15, 1, 'enrolled'), (15, 2, 'enrolled'), (15, 3, 'enrolled'),
(16, 1, 'enrolled'), (16, 2, 'dropped'), (16, 3, 'enrolled'),
(17, 7, 'enrolled'), (17, 8, 'enrolled'), (17, 9, 'enrolled'), (17, 10, 'enrolled'),
(18, 7, 'enrolled'), (18, 8, 'enrolled'), (18, 9, 'enrolled'),
(19, 11, 'enrolled'), (19, 12, 'enrolled'),
(20, 11, 'enrolled'), (20, 12, 'enrolled');

-- GRADES (enrollment_id, assignment1, assignment2, midterm, final_exam, total, letter_grade, graded_by)
-- Total = (assign1*10 + assign2*10 + midterm*30 + final*50) / 100
-- Kevin (id 9): Top performer - mostly As and Bs
INSERT INTO grades (enrollment_id, assignment1, assignment2, midterm, final_exam, total, letter_grade, graded_by) VALUES
(1, 95.00, 92.00, 91.00, 93.00, 93.00, 'A', 4),
(2, 88.00, 90.00, 85.00, 90.00, 88.50, 'B', 4),
(3, 92.00, 95.00, 88.00, 91.00, 91.00, 'A', 5),
(4, 85.00, 88.00, 82.00, 87.00, 86.00, 'B', 5),
(5, 90.00, 87.00, 92.00, 88.00, 89.30, 'B', 6),
(6, 82.00, 85.00, 80.00, 86.00, 84.30, 'B', 6);

-- Sandrine (id 10): Good performer - mostly Bs
INSERT INTO grades (enrollment_id, assignment1, assignment2, midterm, final_exam, total, letter_grade, graded_by) VALUES
(7, 82.00, 78.00, 80.00, 85.00, 82.50, 'B', 4),
(8, 85.00, 82.00, 78.00, 82.00, 81.30, 'B', 4),
(9, 80.00, 85.00, 75.00, 80.00, 79.00, 'C', 5),
(10, 78.00, 80.00, 82.00, 78.00, 79.00, 'C', 5),
(11, 83.00, 79.00, 81.00, 84.00, 82.20, 'B', 6);

-- Eric (id 11): Average performer - mostly Cs
INSERT INTO grades (enrollment_id, assignment1, assignment2, midterm, final_exam, total, letter_grade, graded_by) VALUES
(12, 70.00, 72.00, 68.00, 73.00, 71.40, 'C', 4),
(13, 75.00, 70.00, 65.00, 70.00, 69.50, 'C', 4),
(14, 68.00, 72.00, 70.00, 68.00, 69.20, 'C', 5),
(15, 72.00, 68.00, 74.00, 70.00, 71.00, 'C', 5);

-- Belyse (id 12): Struggling - Cs and Ds
INSERT INTO grades (enrollment_id, assignment1, assignment2, midterm, final_exam, total, letter_grade, graded_by) VALUES
(16, 55.00, 60.00, 52.00, 58.00, 56.30, 'F', 4),
(17, 62.00, 58.00, 55.00, 60.00, 58.50, 'F', 4),
(18, 65.00, 60.00, 62.00, 58.00, 60.50, 'D', 5);

-- Olivier (id 13): Good performer - Bs and Cs
INSERT INTO grades (enrollment_id, assignment1, assignment2, midterm, final_exam, total, letter_grade, graded_by) VALUES
(19, 80.00, 78.00, 82.00, 80.00, 80.20, 'B', 4),
(20, 78.00, 82.00, 75.00, 78.00, 77.50, 'C', 4),
(21, 82.00, 80.00, 78.00, 83.00, 80.70, 'B', 5),
(22, 75.00, 80.00, 72.00, 78.00, 76.20, 'C', 5);

-- Immaculee (id 14): Excellent - top of class
INSERT INTO grades (enrollment_id, assignment1, assignment2, midterm, final_exam, total, letter_grade, graded_by) VALUES
(23, 98.00, 96.00, 95.00, 97.00, 96.60, 'A', 4),
(24, 94.00, 92.00, 90.00, 95.00, 93.10, 'A', 4),
(25, 96.00, 98.00, 92.00, 94.00, 94.60, 'A', 5),
(26, 90.00, 88.00, 92.00, 90.00, 90.00, 'A', 5),
(27, 92.00, 90.00, 88.00, 91.00, 90.20, 'A', 6);

-- Aimable (id 15): Average - Cs and Ds
INSERT INTO grades (enrollment_id, assignment1, assignment2, midterm, final_exam, total, letter_grade, graded_by) VALUES
(28, 68.00, 65.00, 62.00, 68.00, 66.10, 'D', 4),
(29, 70.00, 68.00, 65.00, 72.00, 69.30, 'C', 4),
(30, 65.00, 70.00, 60.00, 65.00, 64.50, 'D', 5);

-- Yvette (id 16): Below average - one dropped so no grade for course 2
INSERT INTO grades (enrollment_id, assignment1, assignment2, midterm, final_exam, total, letter_grade, graded_by) VALUES
(32, 58.00, 55.00, 50.00, 55.00, 54.00, 'F', 4),
(33, 60.00, 58.00, 55.00, 62.00, 58.80, 'F', 5);

-- David (id 17): Good BUS student
INSERT INTO grades (enrollment_id, assignment1, assignment2, midterm, final_exam, total, letter_grade, graded_by) VALUES
(34, 85.00, 82.00, 80.00, 88.00, 84.70, 'B', 7),
(35, 78.00, 80.00, 75.00, 82.00, 79.30, 'C', 7),
(36, 82.00, 85.00, 78.00, 80.00, 80.10, 'B', 7),
(37, 80.00, 78.00, 82.00, 80.00, 80.20, 'B', 7);

-- Ange (id 18): Average BUS student
INSERT INTO grades (enrollment_id, assignment1, assignment2, midterm, final_exam, total, letter_grade, graded_by) VALUES
(38, 72.00, 70.00, 68.00, 75.00, 72.00, 'C', 7),
(39, 68.00, 72.00, 65.00, 70.00, 68.90, 'C', 7),
(40, 75.00, 70.00, 72.00, 73.00, 72.60, 'C', 7);

-- Jean (id 19): Good EEE student
INSERT INTO grades (enrollment_id, assignment1, assignment2, midterm, final_exam, total, letter_grade, graded_by) VALUES
(41, 88.00, 85.00, 82.00, 90.00, 87.10, 'B', 8),
(42, 82.00, 80.00, 78.00, 85.00, 81.60, 'B', 8);

-- Josiane (id 20): Average EEE student
INSERT INTO grades (enrollment_id, assignment1, assignment2, midterm, final_exam, total, letter_grade, graded_by) VALUES
(43, 72.00, 70.00, 68.00, 75.00, 72.00, 'C', 8),
(44, 68.00, 65.00, 70.00, 68.00, 68.10, 'C', 8);

-- ATTENDANCE (enrollment_id, date, status)
-- Kevin: Excellent attendance
INSERT INTO attendance (enrollment_id, date, status) VALUES
(1, '2025-09-01', 'present'), (1, '2025-09-08', 'present'), (1, '2025-09-15', 'present'), (1, '2025-09-22', 'present'), (1, '2025-09-29', 'present'),
(2, '2025-09-02', 'present'), (2, '2025-09-09', 'present'), (2, '2025-09-16', 'present'), (2, '2025-09-23', 'present'), (2, '2025-09-30', 'present'),
(3, '2025-09-03', 'present'), (3, '2025-09-10', 'present'), (3, '2025-09-17', 'present'), (3, '2025-09-24', 'present'), (3, '2025-10-01', 'present'),
(4, '2025-09-01', 'present'), (4, '2025-09-08', 'present'), (4, '2025-09-15', 'late'), (4, '2025-09-22', 'present'), (4, '2025-09-29', 'present'),
(5, '2025-09-04', 'present'), (5, '2025-09-11', 'present'), (5, '2025-09-18', 'present'), (5, '2025-09-25', 'present'), (5, '2025-10-02', 'present'),
(6, '2025-09-05', 'present'), (6, '2025-09-12', 'late'), (6, '2025-09-19', 'present'), (6, '2025-09-26', 'present'), (6, '2025-10-03', 'present');

-- Sandrine: Good attendance
INSERT INTO attendance (enrollment_id, date, status) VALUES
(7, '2025-09-01', 'present'), (7, '2025-09-08', 'present'), (7, '2025-09-15', 'present'), (7, '2025-09-22', 'absent'), (7, '2025-09-29', 'present'),
(8, '2025-09-02', 'present'), (8, '2025-09-09', 'late'), (8, '2025-09-16', 'present'), (8, '2025-09-23', 'present'), (8, '2025-09-30', 'present'),
(9, '2025-09-03', 'present'), (9, '2025-09-10', 'present'), (9, '2025-09-17', 'absent'), (9, '2025-09-24', 'present'), (9, '2025-10-01', 'present'),
(10, '2025-09-04', 'present'), (10, '2025-09-11', 'present'), (10, '2025-09-18', 'late'), (10, '2025-09-25', 'present'), (10, '2025-10-02', 'present'),
(11, '2025-09-05', 'present'), (11, '2025-09-12', 'present'), (11, '2025-09-19', 'present'), (11, '2025-09-26', 'absent'), (11, '2025-10-03', 'present');

-- Eric: Average attendance
INSERT INTO attendance (enrollment_id, date, status) VALUES
(12, '2025-09-01', 'present'), (12, '2025-09-08', 'absent'), (12, '2025-09-15', 'present'), (12, '2025-09-22', 'late'), (12, '2025-09-29', 'absent'),
(13, '2025-09-02', 'present'), (13, '2025-09-09', 'present'), (13, '2025-09-16', 'absent'), (13, '2025-09-23', 'present'), (13, '2025-09-30', 'late'),
(14, '2025-09-03', 'absent'), (14, '2025-09-10', 'present'), (14, '2025-09-17', 'present'), (14, '2025-09-24', 'absent'), (14, '2025-10-01', 'present'),
(15, '2025-09-04', 'present'), (15, '2025-09-11', 'late'), (15, '2025-09-18', 'present'), (15, '2025-09-25', 'absent'), (15, '2025-10-02', 'present');

-- Belyse: Poor attendance
INSERT INTO attendance (enrollment_id, date, status) VALUES
(16, '2025-09-01', 'absent'), (16, '2025-09-08', 'absent'), (16, '2025-09-15', 'present'), (16, '2025-09-22', 'absent'), (16, '2025-09-29', 'absent'),
(17, '2025-09-02', 'late'), (17, '2025-09-09', 'absent'), (17, '2025-09-16', 'absent'), (17, '2025-09-23', 'present'), (17, '2025-09-30', 'absent'),
(18, '2025-09-03', 'absent'), (18, '2025-09-10', 'late'), (18, '2025-09-17', 'absent'), (18, '2025-09-24', 'present'), (18, '2025-10-01', 'absent');

-- Olivier: Good attendance
INSERT INTO attendance (enrollment_id, date, status) VALUES
(19, '2025-09-01', 'present'), (19, '2025-09-08', 'present'), (19, '2025-09-15', 'late'), (19, '2025-09-22', 'present'), (19, '2025-09-29', 'present'),
(20, '2025-09-02', 'present'), (20, '2025-09-09', 'present'), (20, '2025-09-16', 'present'), (20, '2025-09-23', 'late'), (20, '2025-09-30', 'present'),
(21, '2025-09-03', 'present'), (21, '2025-09-10', 'present'), (21, '2025-09-17', 'present'), (21, '2025-09-24', 'present'), (21, '2025-10-01', 'present'),
(22, '2025-09-04', 'present'), (22, '2025-09-11', 'absent'), (22, '2025-09-18', 'present'), (22, '2025-09-25', 'present'), (22, '2025-10-02', 'present');

-- Immaculee: Perfect attendance
INSERT INTO attendance (enrollment_id, date, status) VALUES
(23, '2025-09-01', 'present'), (23, '2025-09-08', 'present'), (23, '2025-09-15', 'present'), (23, '2025-09-22', 'present'), (23, '2025-09-29', 'present'),
(24, '2025-09-02', 'present'), (24, '2025-09-09', 'present'), (24, '2025-09-16', 'present'), (24, '2025-09-23', 'present'), (24, '2025-09-30', 'present'),
(25, '2025-09-03', 'present'), (25, '2025-09-10', 'present'), (25, '2025-09-17', 'present'), (25, '2025-09-24', 'present'), (25, '2025-10-01', 'present'),
(26, '2025-09-04', 'present'), (26, '2025-09-11', 'present'), (26, '2025-09-18', 'present'), (26, '2025-09-25', 'present'), (26, '2025-10-02', 'present'),
(27, '2025-09-05', 'present'), (27, '2025-09-12', 'present'), (27, '2025-09-19', 'present'), (27, '2025-09-26', 'present'), (27, '2025-10-03', 'present');

-- Aimable: Average attendance
INSERT INTO attendance (enrollment_id, date, status) VALUES
(28, '2025-09-01', 'present'), (28, '2025-09-08', 'late'), (28, '2025-09-15', 'absent'), (28, '2025-09-22', 'present'), (28, '2025-09-29', 'present'),
(29, '2025-09-02', 'present'), (29, '2025-09-09', 'present'), (29, '2025-09-16', 'late'), (29, '2025-09-23', 'absent'), (29, '2025-09-30', 'present'),
(30, '2025-09-03', 'absent'), (30, '2025-09-10', 'present'), (30, '2025-09-17', 'present'), (30, '2025-09-24', 'late'), (30, '2025-10-01', 'present');

-- Yvette: Poor attendance
INSERT INTO attendance (enrollment_id, date, status) VALUES
(32, '2025-09-01', 'absent'), (32, '2025-09-08', 'absent'), (32, '2025-09-15', 'late'), (32, '2025-09-22', 'absent'), (32, '2025-09-29', 'absent'),
(33, '2025-09-03', 'late'), (33, '2025-09-10', 'absent'), (33, '2025-09-17', 'present'), (33, '2025-09-24', 'absent'), (33, '2025-10-01', 'absent');

-- David (BUS): Good attendance
INSERT INTO attendance (enrollment_id, date, status) VALUES
(34, '2025-09-02', 'present'), (34, '2025-09-09', 'present'), (34, '2025-09-16', 'present'), (34, '2025-09-23', 'present'), (34, '2025-09-30', 'late'),
(35, '2025-09-03', 'present'), (35, '2025-09-10', 'present'), (35, '2025-09-17', 'late'), (35, '2025-09-24', 'present'), (35, '2025-10-01', 'present'),
(36, '2025-09-04', 'present'), (36, '2025-09-11', 'present'), (36, '2025-09-18', 'present'), (36, '2025-09-25', 'absent'), (36, '2025-10-02', 'present'),
(37, '2025-09-05', 'present'), (37, '2025-09-12', 'present'), (37, '2025-09-19', 'present'), (37, '2025-09-26', 'present'), (37, '2025-10-03', 'present');

-- Ange (BUS): Average attendance
INSERT INTO attendance (enrollment_id, date, status) VALUES
(38, '2025-09-02', 'present'), (38, '2025-09-09', 'absent'), (38, '2025-09-16', 'present'), (38, '2025-09-23', 'late'), (38, '2025-09-30', 'present'),
(39, '2025-09-03', 'late'), (39, '2025-09-10', 'present'), (39, '2025-09-17', 'absent'), (39, '2025-09-24', 'present'), (39, '2025-10-01', 'present'),
(40, '2025-09-04', 'present'), (40, '2025-09-11', 'present'), (40, '2025-09-18', 'late'), (40, '2025-09-25', 'present'), (40, '2025-10-02', 'absent');

-- Jean (EEE): Good attendance
INSERT INTO attendance (enrollment_id, date, status) VALUES
(41, '2025-09-01', 'present'), (41, '2025-09-08', 'present'), (41, '2025-09-15', 'present'), (41, '2025-09-22', 'present'), (41, '2025-09-29', 'late'),
(42, '2025-09-03', 'present'), (42, '2025-09-10', 'present'), (42, '2025-09-17', 'present'), (42, '2025-09-24', 'present'), (42, '2025-10-01', 'present');

-- Josiane (EEE): Average attendance
INSERT INTO attendance (enrollment_id, date, status) VALUES
(43, '2025-09-01', 'present'), (43, '2025-09-08', 'late'), (43, '2025-09-15', 'absent'), (43, '2025-09-22', 'present'), (43, '2025-09-29', 'present'),
(44, '2025-09-03', 'present'), (44, '2025-09-10', 'present'), (44, '2025-09-17', 'late'), (44, '2025-09-24', 'absent'), (44, '2025-10-01', 'present');

-- PAYMENTS (student_id, amount, method, paid_at)
-- Fee per credit = 20,000 FRW. Most courses = 3 credits = 60,000 FRW per course
-- CSC students with 6 courses = 360,000 FRW total; BUS/EEE students with fewer courses
INSERT INTO payments (student_id, amount, method, paid_at) VALUES
-- Kevin: Full payment
(9, 360000.00, 'Mobile Money', '2025-08-15 10:00:00'),
-- Sandrine: Partial payment
(10, 250000.00, 'Bank Transfer', '2025-08-16 11:00:00'),
(10, 50000.00, 'Mobile Money', '2025-09-10 14:30:00'),
-- Eric: Partial payment
(11, 180000.00, 'Cash', '2025-08-18 09:00:00'),
(11, 60000.00, 'Mobile Money', '2025-10-01 16:00:00'),
-- Belyse: Minimal payment
(12, 60000.00, 'Cash', '2025-09-01 10:00:00'),
-- Olivier: Full payment
(13, 240000.00, 'Bank Transfer', '2025-08-14 08:30:00'),
-- Immaculee: Full payment
(14, 300000.00, 'Mobile Money', '2025-08-12 15:00:00'),
-- Aimable: Partial payment
(15, 120000.00, 'Cash', '2025-09-05 11:00:00'),
-- Yvette: Minimal payment
(16, 60000.00, 'Cash', '2025-09-10 10:00:00'),
-- David: Full payment
(17, 240000.00, 'Bank Transfer', '2025-08-20 09:00:00'),
-- Ange: Partial payment
(18, 120000.00, 'Mobile Money', '2025-08-22 14:00:00'),
-- Jean: Full payment
(19, 180000.00, 'Bank Transfer', '2025-08-17 10:00:00'),
-- Josiane: Partial payment
(20, 120000.00, 'Mobile Money', '2025-08-19 11:00:00');

-- COURSE APPROVALS (student_id, course_id, requested_by, status, notes, reviewed_by, created_at)
INSERT INTO course_approvals (student_id, course_id, requested_by, status, notes, reviewed_by, created_at) VALUES
-- Approved requests
(9, 4, 9, 'approved', 'Student meets prerequisites', 2, '2025-08-10 09:00:00'),
(9, 5, 9, 'approved', 'Strong academic record', 2, '2025-08-10 09:05:00'),
(9, 6, 9, 'approved', 'Approved for advanced courses', 2, '2025-08-10 09:10:00'),
(14, 4, 14, 'approved', 'Top performing student', 2, '2025-08-11 10:00:00'),
(14, 5, 14, 'approved', 'Excellent grades in prerequisites', 2, '2025-08-11 10:05:00'),
(10, 4, 10, 'approved', 'Good academic standing', 2, '2025-08-12 11:00:00'),
(10, 5, 10, 'approved', 'Meets requirements', 2, '2025-08-12 11:05:00'),
(11, 4, 11, 'approved', 'Average performance acceptable', 2, '2025-08-13 09:00:00'),
(13, 4, 13, 'approved', 'Satisfactory progress', 2, '2025-08-13 09:30:00'),
-- Pending request
(12, 4, 12, 'pending', 'Requesting enrollment in Database Systems', NULL, '2025-09-15 14:00:00'),
(15, 4, 15, 'pending', 'Wants to take additional course', NULL, '2025-09-16 10:00:00'),
-- Rejected request
(16, 4, 16, 'rejected', 'Insufficient attendance record', 2, '2025-08-14 11:00:00'),
(12, 5, 12, 'rejected', 'Grades below minimum threshold', 2, '2025-08-15 09:00:00');

COMMIT;
