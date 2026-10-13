<?php
$addUserDefaultRole = isset($addUserDefaultRole) ? strtolower((string)$addUserDefaultRole) : '';
?>
<style>
    .add-user-modal {
        position: fixed;
        inset: 0;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 24px;
        background: rgba(15, 23, 42, 0.66);
        z-index: 1400;
    }
    .add-user-modal.active {
        display: flex;
    }
    .add-user-card {
        width: min(760px, 100%);
        max-height: calc(100vh - 48px);
        overflow-y: auto;
        background: var(--card-bg);
        border-radius: 24px;
        box-shadow: 0 24px 60px rgba(15, 23, 42, 0.22);
        padding: 30px;
        position: relative;
    }
    .add-user-close {
        position: absolute;
        top: 18px;
        right: 18px;
        width: 40px;
        height: 40px;
        border: none;
        border-radius: 999px;
        background: #0F172A;
        color: white;
        cursor: pointer;
        font-size: 18px;
    }
    .add-user-header h2 {
        margin: 0;
        font-size: 24px;
        color: var(--text-main);
    }
    .add-user-header p {
        margin: 8px 0 0;
        color: var(--text-muted);
        line-height: 1.6;
    }
    .add-user-alert {
        display: none;
        margin-top: 18px;
        padding: 14px 16px;
        border-radius: 16px;
        border: 1px solid rgba(244, 63, 94, 0.18);
        background: rgba(244, 63, 94, 0.08);
        color: #E11D48;
        font-size: 14px;
        font-weight: 600;
    }
    .add-user-alert.active {
        display: block;
    }
    .add-user-form {
        margin-top: 24px;
    }
    .add-user-section + .add-user-section {
        margin-top: 22px;
    }
    .add-user-section-title {
        margin: 0 0 14px;
        font-size: 13px;
        font-weight: 800;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        color: #64748B;
    }
    .add-user-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px 20px;
    }
    .add-user-field.full {
        grid-column: 1 / -1;
    }
    .add-user-label {
        display: block;
        margin-bottom: 8px;
        font-size: 13px;
        font-weight: 700;
        color: #475569;
    }
    .add-user-input {
        width: 100%;
        box-sizing: border-box;
        padding: 13px 15px;
        border: 1.5px solid #E2E8F0;
        border-radius: 14px;
        background: #FAFBFC;
        font-size: 15px;
        font-family: inherit;
        color: #0F172A;
        outline: none;
        transition: 0.2s ease;
    }
    .add-user-input:focus {
        border-color: #6A5AE0;
        box-shadow: 0 0 0 4px rgba(106, 90, 224, 0.08);
        background: white;
    }
    .add-user-status {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 14px;
        border-radius: 14px;
        background: rgba(16, 185, 129, 0.1);
        color: #059669;
        font-weight: 700;
        font-size: 14px;
        border: 1px solid rgba(16, 185, 129, 0.16);
    }
    .add-user-actions {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 28px;
    }
    .add-user-actions .btn {
        min-width: 140px;
    }
    .btn-muted {
        background: #E2E8F0;
        color: #334155;
    }
    @media (max-width: 768px) {
        .add-user-card {
            padding: 24px 20px;
        }
        .add-user-grid {
            grid-template-columns: 1fr;
        }
        .add-user-field.full {
            grid-column: auto;
        }
    }
</style>

<div class="add-user-modal" id="addUserModal">
    <div class="add-user-card">
        <button type="button" class="add-user-close" onclick="closeAddUserModal()">&#10005;</button>

        <div class="add-user-header">
            <h2>Add New User</h2>
            <p>Create a new dashboard user without leaving the current page.</p>
        </div>

        <div class="add-user-alert" id="addUserErrorBox"></div>

        <form class="add-user-form" id="addUserForm">
            <input type="hidden" name="status" value="active">

            <div class="add-user-section">
                <div class="add-user-section-title">Personal Information</div>
                <div class="add-user-grid">
                    <div class="add-user-field full">
                        <label class="add-user-label" for="add_user_fullname">Full Name</label>
                        <input class="add-user-input" type="text" id="add_user_fullname" name="fullname" required>
                    </div>

                    <div class="add-user-field">
                        <label class="add-user-label" for="add_user_phone">Phone Number</label>
                        <input class="add-user-input" type="text" id="add_user_phone" name="phone">
                    </div>

                    <div class="add-user-field full">
                        <label class="add-user-label" for="add_user_address">Address</label>
                        <input class="add-user-input" type="text" id="add_user_address" name="address">
                    </div>
                </div>
            </div>

            <div class="add-user-section">
                <div class="add-user-section-title">Account Credentials</div>
                <div class="add-user-grid">
                    <div class="add-user-field">
                        <label class="add-user-label" for="add_user_username">Username</label>
                        <input class="add-user-input" type="text" id="add_user_username" name="username" required autocomplete="username" placeholder="e.g. juan_dela_cruz">
                    </div>

                    <div class="add-user-field">
                        <label class="add-user-label" for="add_user_password">Password</label>
                        <input class="add-user-input" type="password" id="add_user_password" name="password" required>
                    </div>

                    <div class="add-user-field">
                        <label class="add-user-label" for="add_user_role">User Role</label>
                        <select class="add-user-input" id="add_user_role" name="role" required>
                            <option value="">Select Role</option>
                            <option value="admin">Admin</option>
                            <option value="collector">Collector</option>
                            <option value="borrower">Borrower</option>
                        </select>
                    </div>

                    <div class="add-user-field">
                        <label class="add-user-label">Account Status</label>
                        <div class="add-user-status">
                            <i class="ph ph-check-circle"></i>
                            Active
                        </div>
                    </div>
                </div>
            </div>

            <div class="add-user-actions">
                <button type="submit" class="btn btn-success" id="addUserSubmitButton">Create User</button>
                <button type="button" class="btn btn-muted" onclick="closeAddUserModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
    (function() {
        const modal = document.getElementById('addUserModal');
        const form = document.getElementById('addUserForm');
        const errorBox = document.getElementById('addUserErrorBox');
        const roleField = document.getElementById('add_user_role');
        const submitButton = document.getElementById('addUserSubmitButton');
        const defaultRole = <?php echo json_encode($addUserDefaultRole); ?>;

        function resetAddUserForm() {
            form.reset();
            form.elements.status.value = 'active';
            roleField.value = defaultRole || '';
            errorBox.textContent = '';
            errorBox.classList.remove('active');
            submitButton.disabled = false;
            submitButton.textContent = 'Create User';
        }

        window.openAddUserModal = function(role) {
            resetAddUserForm();
            if (role) {
                roleField.value = String(role).toLowerCase();
            }
            modal.classList.add('active');
            document.getElementById('add_user_fullname').focus();
        };

        window.closeAddUserModal = function() {
            modal.classList.remove('active');
            resetAddUserForm();
        };

        modal.addEventListener('click', function(event) {
            if (event.target === modal) {
                closeAddUserModal();
            }
        });

        form.addEventListener('submit', async function(event) {
            event.preventDefault();
            errorBox.textContent = '';
            errorBox.classList.remove('active');
            submitButton.disabled = true;
            submitButton.textContent = 'Creating...';

            try {
                const response = await fetch('add_user.php', {
                    method: 'POST',
                    body: new FormData(form)
                });
                const payload = await response.json();

                if (payload.status !== 'success') {
                    throw new Error(payload.message || 'Unable to create this user right now.');
                }

                const redirectRole = String(payload.role || roleField.value || defaultRole || '').toLowerCase();
                if (redirectRole) {
                    const redirectUrl = new URL('manage_users_list.php', window.location.href);
                    redirectUrl.searchParams.set('role', redirectRole);
                    redirectUrl.searchParams.set('created', '1');
                    window.location.href = redirectUrl.toString();
                    return;
                }

                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set('created', '1');
                window.location.href = currentUrl.toString();
            } catch (error) {
                errorBox.textContent = error.message || 'Unable to create this user right now.';
                errorBox.classList.add('active');
                submitButton.disabled = false;
                submitButton.textContent = 'Create User';
            }
        });
    })();
</script>
