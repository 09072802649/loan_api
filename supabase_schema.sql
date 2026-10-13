-- ==============================================================================
-- GOCASH™ LOAN MANAGEMENT SYSTEM - SUPABASE POSTGRESQL SCHEMA & INITIAL SEED
-- ==============================================================================
-- Generated for Supabase (PostgreSQL 15+)
-- Converts MySQL/MariaDB schema.sql into native PostgreSQL with RLS & Seed Data
-- ==============================================================================

-- 1. CLEANUP (Optional: Uncomment if resetting tables)
-- DROP TABLE IF EXISTS payments CASCADE;
-- DROP TABLE IF EXISTS remittances CASCADE;
-- DROP TABLE IF EXISTS loan_installments CASCADE;
-- DROP TABLE IF EXISTS loan_requests CASCADE;
-- DROP TABLE IF EXISTS loans CASCADE;
-- DROP TABLE IF EXISTS notifications CASCADE;
-- DROP TABLE IF EXISTS users CASCADE;

-- -----------------------------------------------------------------------------
-- 2. TABLE DEFINITIONS
-- -----------------------------------------------------------------------------

-- USERS TABLE
CREATE TABLE IF NOT EXISTS users (
  id BIGSERIAL PRIMARY KEY,
  fullname VARCHAR(255),
  username VARCHAR(255) UNIQUE NOT NULL,
  password VARCHAR(255),
  role VARCHAR(50) DEFAULT 'borrower' CHECK (role IN ('admin', 'collector', 'borrower')),
  status VARCHAR(50) DEFAULT 'active' CHECK (status IN ('active', 'inactive')),
  created_at TIMESTAMPTZ DEFAULT timezone('utc'::text, now()) NOT NULL,
  last_login TIMESTAMPTZ,
  phone VARCHAR(50),
  address TEXT,
  collector_id BIGINT REFERENCES users(id) ON DELETE SET NULL
);

-- LOANS TABLE
CREATE TABLE IF NOT EXISTS loans (
  id BIGSERIAL PRIMARY KEY,
  borrower_id BIGINT REFERENCES users(id) ON DELETE CASCADE NOT NULL,
  collector_id BIGINT REFERENCES users(id) ON DELETE SET NULL NOT NULL,
  loan_account_number VARCHAR(50),
  principal_amount NUMERIC(12,2) NOT NULL,
  total_paid NUMERIC(10,2) DEFAULT 0.00,
  remaining_balance NUMERIC(10,2) DEFAULT 0.00,
  interest_rate NUMERIC(10,2) DEFAULT 0.00,
  loan_term INTEGER DEFAULT 1,
  payment_frequency VARCHAR(50) DEFAULT 'monthly' CHECK (payment_frequency IN ('daily', 'weekly', 'monthly')),
  total_amount NUMERIC(12,2) DEFAULT 0.00,
  installment_amount NUMERIC(10,2) DEFAULT 0.00,
  number_of_payments INTEGER DEFAULT 1,
  start_date DATE,
  status VARCHAR(50) DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'rejected', 'ongoing', 'paid', 'completed')),
  created_at TIMESTAMPTZ DEFAULT timezone('utc'::text, now()) NOT NULL,
  borrower_name VARCHAR(255),
  borrower_contact VARCHAR(50),
  borrower_address TEXT,
  balance NUMERIC(10,2) DEFAULT 0.00,
  due_date DATE
);

-- LOAN INSTALLMENTS TABLE
CREATE TABLE IF NOT EXISTS loan_installments (
  id BIGSERIAL PRIMARY KEY,
  loan_id BIGINT REFERENCES loans(id) ON DELETE CASCADE NOT NULL,
  installment_number INTEGER NOT NULL,
  due_date DATE NOT NULL,
  amount NUMERIC(10,2) DEFAULT 0.00,
  status VARCHAR(50) DEFAULT 'pending' CHECK (status IN ('pending', 'paid', 'overdue')),
  paid_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ DEFAULT timezone('utc'::text, now()) NOT NULL
);

-- LOAN REQUESTS TABLE
CREATE TABLE IF NOT EXISTS loan_requests (
  id BIGSERIAL PRIMARY KEY,
  borrower_id BIGINT REFERENCES users(id) ON DELETE CASCADE NOT NULL,
  collector_id BIGINT REFERENCES users(id) ON DELETE SET NULL NOT NULL,
  principal_amount NUMERIC(12,2) NOT NULL,
  interest_rate NUMERIC(10,2) DEFAULT 0.00,
  loan_term INTEGER DEFAULT 1,
  payment_frequency VARCHAR(50) CHECK (payment_frequency IN ('daily', 'weekly', 'monthly')),
  total_amount NUMERIC(12,2) DEFAULT 0.00,
  installment_amount NUMERIC(10,2) DEFAULT 0.00,
  number_of_payments INTEGER DEFAULT 1,
  status VARCHAR(50) DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'rejected')),
  created_at TIMESTAMPTZ DEFAULT timezone('utc'::text, now()) NOT NULL,
  updated_at TIMESTAMPTZ DEFAULT timezone('utc'::text, now()) NOT NULL
);

-- REMITTANCES TABLE
CREATE TABLE IF NOT EXISTS remittances (
  id BIGSERIAL PRIMARY KEY,
  collector_id BIGINT REFERENCES users(id) ON DELETE CASCADE NOT NULL,
  remittance_date TIMESTAMPTZ,
  total_amount NUMERIC(10,2) DEFAULT 0.00,
  transaction_count INTEGER DEFAULT 0,
  status VARCHAR(50) DEFAULT 'pending' CHECK (status IN ('pending', 'received')),
  received_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ DEFAULT timezone('utc'::text, now()) NOT NULL
);

-- PAYMENTS TABLE
CREATE TABLE IF NOT EXISTS payments (
  id BIGSERIAL PRIMARY KEY,
  loan_id BIGINT REFERENCES loans(id) ON DELETE CASCADE NOT NULL,
  installment_id BIGINT REFERENCES loan_installments(id) ON DELETE SET NULL,
  amount NUMERIC(10,2) NOT NULL,
  payment_date TIMESTAMPTZ DEFAULT timezone('utc'::text, now()) NOT NULL,
  proof_image VARCHAR(255),
  status VARCHAR(50) DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'rejected')),
  remitted BOOLEAN DEFAULT FALSE,
  remittance_id BIGINT REFERENCES remittances(id) ON DELETE SET NULL,
  borrower_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
  collector_id BIGINT REFERENCES users(id) ON DELETE SET NULL
);

-- NOTIFICATIONS TABLE
CREATE TABLE IF NOT EXISTS notifications (
  id BIGSERIAL PRIMARY KEY,
  user_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
  title VARCHAR(255),
  message TEXT,
  type VARCHAR(50) DEFAULT 'general',
  related_id BIGINT,
  is_read BOOLEAN DEFAULT FALSE,
  status VARCHAR(50) DEFAULT 'unread',
  created_at TIMESTAMPTZ DEFAULT timezone('utc'::text, now()) NOT NULL
);

-- -----------------------------------------------------------------------------
-- 3. ROW LEVEL SECURITY (RLS) POLICIES
-- Enables open CRUD access for the web app client using the anon key.
-- -----------------------------------------------------------------------------

ALTER TABLE users ENABLE ROW LEVEL SECURITY;
ALTER TABLE loans ENABLE ROW LEVEL SECURITY;
ALTER TABLE loan_installments ENABLE ROW LEVEL SECURITY;
ALTER TABLE loan_requests ENABLE ROW LEVEL SECURITY;
ALTER TABLE remittances ENABLE ROW LEVEL SECURITY;
ALTER TABLE payments ENABLE ROW LEVEL SECURITY;
ALTER TABLE notifications ENABLE ROW LEVEL SECURITY;

-- Allow anonymous / authenticated read and write for the client app
DO $$
BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_policies WHERE policyname = 'Public access to users') THEN
    CREATE POLICY "Public access to users" ON users FOR ALL USING (true) WITH CHECK (true);
  END IF;
  IF NOT EXISTS (SELECT 1 FROM pg_policies WHERE policyname = 'Public access to loans') THEN
    CREATE POLICY "Public access to loans" ON loans FOR ALL USING (true) WITH CHECK (true);
  END IF;
  IF NOT EXISTS (SELECT 1 FROM pg_policies WHERE policyname = 'Public access to loan_installments') THEN
    CREATE POLICY "Public access to loan_installments" ON loan_installments FOR ALL USING (true) WITH CHECK (true);
  END IF;
  IF NOT EXISTS (SELECT 1 FROM pg_policies WHERE policyname = 'Public access to loan_requests') THEN
    CREATE POLICY "Public access to loan_requests" ON loan_requests FOR ALL USING (true) WITH CHECK (true);
  END IF;
  IF NOT EXISTS (SELECT 1 FROM pg_policies WHERE policyname = 'Public access to remittances') THEN
    CREATE POLICY "Public access to remittances" ON remittances FOR ALL USING (true) WITH CHECK (true);
  END IF;
  IF NOT EXISTS (SELECT 1 FROM pg_policies WHERE policyname = 'Public access to payments') THEN
    CREATE POLICY "Public access to payments" ON payments FOR ALL USING (true) WITH CHECK (true);
  END IF;
  IF NOT EXISTS (SELECT 1 FROM pg_policies WHERE policyname = 'Public access to notifications') THEN
    CREATE POLICY "Public access to notifications" ON notifications FOR ALL USING (true) WITH CHECK (true);
  END IF;
END $$;

-- -----------------------------------------------------------------------------
-- 4. SEED DATA (Imported from loan_monitoring_db.sql)
-- -----------------------------------------------------------------------------

-- Users Seed
INSERT INTO users (id, fullname, username, password, role, status, created_at, last_login, phone, address, collector_id)
VALUES
  (5, 'System Admin', 'admin', '$2y$10$1ajKAStQzOnayCIiNs6y5uSDWqYYAXJpvK6ySjPFZKtVjYcZHNe5e', 'admin', 'active', '2026-03-15 07:11:27+00', '2026-03-17 01:46:52+00', NULL, NULL, NULL),
  (6, 'satu', 'satu', '$2y$10$j3CnPNikEmEgyVTAbUdAQuzcXRq6fpxHejmFl7CRSsrShjnkoptRS', 'collector', 'active', '2026-03-15 07:24:56+00', NULL, '09132154564', 'Metro Sector A', NULL),
  (10, 'chase', 'chase', '$2y$10$rS76vY/xd8zGkfNqEsJ9YOvM8q9kQVW6cyk/yDGlVQdg0AKwsWU9C', 'borrower', 'active', '2026-03-15 07:33:15+00', NULL, '0612346494', 'gahahajaja', 6),
  (11, 'Chasie', 'chasie', '$2y$10$gk.gOGF.rcljW37gL6C/zeFpKObm8HgcplglMazSW/1lJyEPNcB/y', 'borrower', 'active', '2026-03-15 08:01:30+00', NULL, '09213425299', '#14 lombus st.', 6)
ON CONFLICT (id) DO NOTHING;

-- Adjust sequence for users
SELECT setval('users_id_seq', (SELECT GREATEST(MAX(id), 12) FROM users));

-- Loans Seed
INSERT INTO loans (id, borrower_id, collector_id, loan_account_number, principal_amount, total_paid, remaining_balance, interest_rate, loan_term, payment_frequency, total_amount, installment_amount, number_of_payments, start_date, status, created_at, borrower_name, borrower_contact, borrower_address, balance, due_date)
VALUES
  (2, 10, 6, 'GC-2026-000002', 2000.00, 2400.00, 0.00, 20.00, 2, 'daily', 2400.00, 46.15, 52, '2026-03-15', 'completed', '2026-03-15 07:28:20+00', 'chase', '0612346494', 'gahahajaja', 0.00, NULL),
  (3, 11, 6, 'GC-2026-000003', 1500.00, 800.00, 700.00, 20.00, 2, 'daily', 1800.00, 34.62, 52, '2026-03-17', 'ongoing', '2026-03-15 08:00:38+00', 'Chasie', '09213425299', '#14 lombus st.', 700.00, NULL)
ON CONFLICT (id) DO NOTHING;

SELECT setval('loans_id_seq', (SELECT GREATEST(MAX(id), 4) FROM loans));

-- Loan Requests Seed
INSERT INTO loan_requests (id, borrower_id, collector_id, principal_amount, interest_rate, loan_term, payment_frequency, total_amount, installment_amount, number_of_payments, status, created_at, updated_at)
VALUES
  (1, 10, 6, 2000.00, 20.00, 2, 'daily', 2400.00, 46.15, 52, 'pending', '2026-03-16 18:30:45+00', '2026-03-16 18:30:45+00')
ON CONFLICT (id) DO NOTHING;

SELECT setval('loan_requests_id_seq', (SELECT GREATEST(MAX(id), 2) FROM loan_requests));

-- Remittances Seed
INSERT INTO remittances (id, collector_id, remittance_date, total_amount, transaction_count, status, received_at, created_at)
VALUES
  (1, 6, '2026-03-17 02:30:00+00', 2400.00, 2, 'received', '2026-03-17 03:00:00+00', '2026-03-17 02:30:00+00')
ON CONFLICT (id) DO NOTHING;

SELECT setval('remittances_id_seq', (SELECT GREATEST(MAX(id), 2) FROM remittances));

-- Payments Seed
INSERT INTO payments (id, loan_id, installment_id, amount, payment_date, proof_image, status, remitted, remittance_id, borrower_id, collector_id)
VALUES
  (5, 2, NULL, 300.00, '2026-03-17 01:50:36+00', '1773683436_proof_1773683434957.jpg', 'approved', true, 1, 10, 6),
  (9, 2, NULL, 2100.00, '2026-03-17 02:10:40+00', '1773684640_proof_1773684640657.jpg', 'approved', true, 1, 10, 6),
  (10, 3, NULL, 800.00, '2026-03-17 02:53:33+00', '1773687213_proof_1773687213730.jpg', 'approved', false, NULL, 11, 6)
ON CONFLICT (id) DO NOTHING;

SELECT setval('payments_id_seq', (SELECT GREATEST(MAX(id), 11) FROM payments));

-- Notifications Seed
INSERT INTO notifications (id, user_id, message, is_read, created_at, type, related_id, status, title)
VALUES
  (1, NULL, 'chase submitted a new loan request for PHP 2,000.00.', false, '2026-03-15 07:28:20+00', 'loan_request', 2, 'unread', 'New loan request submitted'),
  (2, NULL, 'chase''s loan request was rejected.', false, '2026-03-15 07:28:32+00', 'loan_rejected', 1, 'unread', 'Loan rejected'),
  (3, NULL, 'chase has been approved and the borrower account was created.', false, '2026-03-15 07:33:16+00', 'loan_approved', 2, 'unread', 'Loan approved'),
  (4, NULL, 'Chasie submitted a new loan request for PHP 1,500.00.', false, '2026-03-15 08:00:38+00', 'loan_request', 3, 'unread', 'New loan request submitted'),
  (5, NULL, 'Chasie has been approved and the borrower account was created.', false, '2026-03-15 08:01:30+00', 'loan_approved', 3, 'unread', 'Loan approved'),
  (6, NULL, 'chase has an overdue installment #1 due on Mar 16, 2026.', false, '2026-03-16 17:46:52+00', 'overdue_payment', 1, 'unread', 'Overdue payment alert'),
  (7, 5, 'Payment of ₱2,100.00 received from borrower ID 10', false, '2026-03-16 18:10:40+00', 'payment_recorded', 9, 'unread', 'New Payment Recorded'),
  (8, NULL, 'chase submitted a reloan request for PHP 2,000.00.', false, '2026-03-16 18:30:45+00', 'reloan_request', 1, 'unread', 'New reloan request submitted'),
  (9, 5, 'Payment of ₱800.00 received from borrower ID 11', false, '2026-03-16 18:53:33+00', 'payment_recorded', 10, 'unread', 'New Payment Recorded')
ON CONFLICT (id) DO NOTHING;

SELECT setval('notifications_id_seq', (SELECT GREATEST(MAX(id), 10) FROM notifications));
