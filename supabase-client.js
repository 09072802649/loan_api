/**
 * ==============================================================================
 * GoCash™ Microfinance - Supabase Integration Client & Service Layer
 * ==============================================================================
 * 
 * Instructions:
 * 1. Replace SUPABASE_URL and SUPABASE_ANON_KEY with your own project credentials
 *    from your Supabase dashboard (Project Settings > API).
 * 2. In index.html, include the Supabase CDN script in your <head> or before this file:
 *    <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
 *    <script src="supabase-client.js"></script>
 */

// --- 1. CONFIGURATION & CLIENT INITIALIZATION ---
const SUPABASE_CONFIG = {
  url: 'https://bvaoroopvnzytmcrqkjb.supabase.co', // Your Supabase Project URL
  anonKey: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImJ2YW9yb29wdm56eXRtY3Jxa2piIiwicm9sZSI6ImFub24iLCJpYXQiOjE3OTE3ODY0ODUsImV4cCI6MjEwNzM2MjQ4NX0.QRRYQBXaTG6As31pTq9BqVGaBu-ADtO9ZkIWWf5VFvo' // Your anon public API Key
};

// Resilient initialization: resolves whenever window.supabase is ready
function getSupabaseClient() {
  if (window.gocashSupabase) return window.gocashSupabase;
  if (typeof window.supabase !== 'undefined' && window.supabase.createClient) {
    window.gocashSupabase = window.supabase.createClient(SUPABASE_CONFIG.url, SUPABASE_CONFIG.anonKey);
    return window.gocashSupabase;
  }
  return null;
}

const supabase = getSupabaseClient();

// --- 2. GOCASH DATA SERVICES ---

const GoCashService = {
  /**
   * Fetch all active loans with borrower details
   */
  async getLoans() {
    const client = getSupabaseClient();
    if (!client) return [];
    const { data, error } = await client
      .from('loans')
      .select('*')
      .order('id', { ascending: false });

    if (error) {
      console.error('Error fetching loans:', error);
      throw error;
    }
    return data;
  },

  /**
   * Create a new loan directly in Supabase PostgreSQL
   */
  async createLoan(payload) {
    const client = getSupabaseClient();
    if (!client) return null;

    let borrowerId = payload.borrower_id;

    // Ensure borrower user exists in the users table to satisfy foreign key constraint
    if (!borrowerId || isNaN(borrowerId)) {
      const cleanName = (payload.borrower_name || '').trim();
      if (cleanName) {
        // Check if user already exists
        const { data: existingUser } = await client
          .from('users')
          .select('id')
          .ilike('fullname', cleanName)
          .limit(1)
          .maybeSingle();

        if (existingUser && existingUser.id) {
          borrowerId = existingUser.id;
        } else {
          // Create borrower in users table
          const baseUsername = cleanName.toLowerCase().replace(/[^a-z0-9]/g, '') || 'borrower';
          const uniqueUsername = `${baseUsername}_${Date.now().toString().slice(-4)}`;
          const { data: newUser } = await client
            .from('users')
            .insert([{
              fullname: cleanName,
              username: uniqueUsername,
              role: 'borrower',
              status: 'active',
              phone: payload.borrower_contact || null,
              address: payload.borrower_address || null,
              collector_id: payload.collector_id || 6
            }])
            .select('id')
            .maybeSingle();

          if (newUser && newUser.id) {
            borrowerId = newUser.id;
          }
        }
      }
    }

    // Default to existing seed borrower (id: 10 or 11) if not found
    if (!borrowerId) {
      const { data: anyBorrower } = await client
        .from('users')
        .select('id')
        .eq('role', 'borrower')
        .limit(1)
        .maybeSingle();
      borrowerId = anyBorrower ? anyBorrower.id : 10;
    }

    const loanPayload = {
      borrower_id: borrowerId,
      collector_id: payload.collector_id || 6,
      loan_account_number: payload.loan_account_number,
      principal_amount: parseFloat(payload.principal_amount),
      total_paid: parseFloat(payload.total_paid || 0),
      remaining_balance: parseFloat(payload.remaining_balance || payload.balance || payload.total_amount),
      interest_rate: parseFloat(payload.interest_rate || 20),
      loan_term: parseInt(payload.loan_term || 2),
      payment_frequency: payload.payment_frequency || 'daily',
      total_amount: parseFloat(payload.total_amount),
      installment_amount: parseFloat(payload.installment_amount),
      number_of_payments: parseInt(payload.number_of_payments || 52),
      start_date: payload.start_date || new Date().toISOString().split('T')[0],
      status: payload.status || 'ongoing',
      borrower_name: payload.borrower_name,
      borrower_contact: payload.borrower_contact,
      borrower_address: payload.borrower_address,
      balance: parseFloat(payload.balance || payload.remaining_balance || payload.total_amount)
    };

    const { data, error } = await client
      .from('loans')
      .insert([loanPayload])
      .select()
      .single();

    if (error) {
      console.error('Error inserting loan into Supabase:', error);
      throw error;
    }

    return data;
  },

  /**
   * Fetch pending and processed loan requests
   */
  async getLoanRequests() {
    const client = getSupabaseClient();
    if (!client) return [];
    const { data, error } = await client
      .from('loan_requests')
      .select('*, borrower:users!loan_requests_borrower_id_fkey(*)')
      .order('id', { ascending: false });

    if (error) {
      console.error('Error fetching loan requests:', error);
      throw error;
    }
    return data;
  },

  /**
   * Submit a new loan request
   */
  async submitLoanRequest(payload) {
    const client = getSupabaseClient();
    if (!client) return null;
    const { data, error } = await client
      .from('loan_requests')
      .insert([{
        borrower_id: payload.borrower_id,
        collector_id: payload.collector_id,
        principal_amount: payload.principal_amount,
        interest_rate: payload.interest_rate || 20.00,
        loan_term: payload.loan_term || 2,
        payment_frequency: payload.payment_frequency || 'daily',
        total_amount: payload.total_amount,
        installment_amount: payload.installment_amount,
        number_of_payments: payload.number_of_payments,
        status: 'pending'
      }])
      .select()
      .single();

    if (error) {
      console.error('Error submitting loan request:', error);
      throw error;
    }
    return data;
  },

  /**
   * Fetch recorded payments
   */
  async getPayments() {
    const client = getSupabaseClient();
    if (!client) return [];
    const { data, error } = await client
      .from('payments')
      .select('*, loan:loans(*), borrower:users!payments_borrower_id_fkey(*)')
      .order('id', { ascending: false });

    if (error) {
      console.error('Error fetching payments:', error);
      throw error;
    }
    return data;
  },

  /**
   * Submit a payment with receipt / proof image
   */
  async submitPayment(payload) {
    const client = getSupabaseClient();
    if (!client) return null;
    const { data, error } = await client
      .from('payments')
      .insert([{
        loan_id: payload.loan_id,
        installment_id: payload.installment_id || null,
        amount: payload.amount,
        proof_image: payload.proof_image || null,
        status: 'approved',
        remitted: false,
        borrower_id: payload.borrower_id,
        collector_id: payload.collector_id
      }])
      .select()
      .single();

    if (error) {
      console.error('Error recording payment:', error);
      throw error;
    }

    return data;
  },

  /**
   * Upload payment proof receipt to Supabase Storage bucket 'payment-proofs'
   */
  async uploadProofImage(file) {
    const client = getSupabaseClient();
    if (!client) return null;
    const fileExt = file.name.split('.').pop();
    const fileName = `${Date.now()}_proof_${Math.random().toString(36).substring(7)}.${fileExt}`;
    const filePath = `receipts/${fileName}`;

    const { data, error } = await client.storage
      .from('payment-proofs')
      .upload(filePath, file);

    if (error) {
      console.error('Storage upload error:', error);
      throw error;
    }

    // Get public URL
    const { data: publicUrlData } = client.storage
      .from('payment-proofs')
      .getPublicUrl(filePath);

    return publicUrlData.publicUrl;
  },

  /**
   * Fetch remittances
   */
  async getRemittances() {
    const client = getSupabaseClient();
    if (!client) return [];
    const { data, error } = await client
      .from('remittances')
      .select('*, collector:users!remittances_collector_id_fkey(*)')
      .order('id', { ascending: false });

    if (error) {
      console.error('Error fetching remittances:', error);
      throw error;
    }
    return data;
  },

  /**
   * Realtime subscription for live notifications or payment updates
   */
  subscribeToPayments(callback) {
    const client = getSupabaseClient();
    if (!client) return null;
    return client
      .channel('realtime:payments')
      .on('postgres_changes', { event: 'INSERT', schema: 'public', table: 'payments' }, payload => {
        callback(payload.new);
      })
      .subscribe();
  }
};

// Expose globally for vanilla browser scripts
if (typeof window !== 'undefined') {
  window.GoCashService = GoCashService;
  window.getSupabaseClient = getSupabaseClient;
  window.gocashSupabase = getSupabaseClient();
}
