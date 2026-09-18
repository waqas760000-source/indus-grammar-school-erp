<?php
/**
 * Indus Grammar School ERP - School Settings Submodule
 * Version 7.0.0 — Commercial Redesign (Metadata, Contact Info, Formats & Academic Sessions)
 */

$pageTitle = 'School Settings';
$breadcrumbActive = 'School Settings';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('system_settings');

$db = Database::getConnection();

// Load current configuration settings from DB
$settings = [];
try {
    $settings = $db->query("SELECT * FROM school_settings LIMIT 1")->fetch();
} catch (Exception $e) {}

if (!$settings) {
    $settings = [
        'school_name'              => SCHOOL_NAME,
        'school_logo'              => null,
        'school_address'           => SCHOOL_ADDRESS,
        'city'                     => 'Karachi',
        'phone_number'             => SCHOOL_PHONE,
        'whatsapp_number'          => SCHOOL_PHONE,
        'email'                    => SCHOOL_EMAIL,
        'website'                  => 'www.indus.edu.pk',
        'principal_name'           => 'Dr. Sajid Ali',
        'owner_name'               => SCHOOL_OWNER,
        'registration_number'      => 'REG-IGS-2026',
        'current_academic_session' => CURRENT_ACADEMIC_YEAR,
        'timezone'                 => 'Asia/Karachi',
        'currency'                 => 'PKR',
        'date_format'              => 'Y-m-d'
    ];
}
?>

<!-- Custom Styling for School Settings Module -->
<style>
:root {
    --primary-navy: #0F172A;
    --primary-blue: #1D4ED8;
    --secondary-blue: #2563EB;
    --light-blue: #EFF6FF;
    --page-background: #F1F5F9;
    --card-white: #FFFFFF;
    --border-color: #E2E8F0;
    --main-text: #1E293B;
    --muted-text: #64748B;
    --success-color: #16A34A;
    --warning-color: #D97706;
    --danger-color: #DC2626;
    --purple-color: #7C3AED;
}

/* Page Outer Canvas */
.settings-page-container {
    background-color: var(--page-background);
    border-radius: 18px;
    padding: 1.5rem;
    min-height: calc(100vh - 100px);
}

/* Hero Header Banner (Navy/Royal Blue Gradient) */
.settings-hero-banner {
    background: linear-gradient(135deg, var(--primary-navy) 0%, #1e3a8a 55%, var(--secondary-blue) 100%);
    border-radius: 16px;
    color: #ffffff;
    padding: 1.75rem 2rem;
    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.18);
    position: relative;
    overflow: hidden;
}

.settings-hero-banner::after {
    content: '';
    position: absolute;
    right: -40px;
    bottom: -40px;
    width: 240px;
    height: 240px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.06);
    pointer-events: none;
}

.hero-icon-box {
    width: 54px;
    height: 54px;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(8px);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.6rem;
    color: #ffffff;
    border: 1px solid rgba(255, 255, 255, 0.25);
}

/* KPI Summary Cards */
.settings-kpi-card {
    background: var(--card-white);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 1.35rem 1.5rem;
    box-shadow: 0 4px 16px rgba(15, 23, 42, 0.04);
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.settings-kpi-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 24px -6px rgba(15, 23, 42, 0.09);
    border-color: #cbd5e1;
}

.kpi-accent-blue   { border-top: 4px solid var(--primary-blue); }
.kpi-accent-amber  { border-top: 4px solid var(--warning-color); }
.kpi-accent-green  { border-top: 4px solid var(--success-color); }
.kpi-accent-purple { border-top: 4px solid var(--purple-color); }

.kpi-icon {
    width: 46px;
    height: 46px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

.kpi-icon-blue   { background-color: var(--light-blue); color: var(--primary-blue); }
.kpi-icon-amber  { background-color: #fffbeb; color: var(--warning-color); }
.kpi-icon-green  { background-color: #f0fdf4; color: var(--success-color); }
.kpi-icon-purple { background-color: #f3e8ff; color: var(--purple-color); }

.kpi-value-text {
    font-size: 1.35rem;
    font-weight: 800;
    color: var(--primary-navy);
    line-height: 1.2;
    margin-top: 0.4rem;
    margin-bottom: 0.2rem;
    word-break: break-all;
}

.kpi-label-text {
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--muted-text);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Form Panel Styling */
.glass-panel {
    background: var(--card-white);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    box-shadow: 0 4px 12px -2px rgba(15, 23, 42, 0.04);
    overflow: hidden;
}

.panel-header {
    padding: 1.1rem 1.5rem;
    border-bottom: 1px solid var(--border-color);
    background: var(--card-white);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.panel-title {
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--primary-navy);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.6rem;
}

.section-label-header {
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--primary-blue);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid var(--light-blue);
    margin-bottom: 1.25rem;
}

/* Sidebar Gateway Links */
.sidebar-gateway-item {
    background: #f8fafc;
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 0.9rem 1.1rem;
    display: flex;
    align-items: center;
    gap: 0.85rem;
    text-decoration: none;
    color: var(--main-text);
    font-weight: 600;
    font-size: 0.9rem;
    transition: all 0.2s ease;
}

.sidebar-gateway-item:hover {
    background: var(--card-white);
    border-color: #bfdbfe;
    color: var(--primary-blue);
    transform: translateX(4px);
    box-shadow: 0 4px 12px rgba(29, 78, 216, 0.08);
}

.sidebar-gateway-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: var(--light-blue);
    color: var(--primary-blue);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
}
</style>

<div class="settings-page-container">

    <!-- 1. Breadcrumb Navigation -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0 small">
            <li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>/dashboard.php" class="text-decoration-none text-muted"><i class="fa-solid fa-house me-1"></i>Dashboard</a></li>
            <li class="breadcrumb-item text-muted">Administration</li>
            <li class="breadcrumb-item active fw-semibold text-primary" aria-current="page">School Settings</li>
        </ol>
    </nav>

    <!-- 2. Hero Header Banner -->
    <div class="settings-hero-banner mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 position-relative" style="z-index: 1;">
            <div class="d-flex align-items-center gap-3">
                <div class="hero-icon-box">
                    <i class="fa-solid fa-sliders"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h2 class="fw-bold mb-0 text-white fs-3">School Settings</h2>
                        <span class="badge bg-white text-primary px-3 py-1 rounded-pill small fw-semibold">Global Configuration</span>
                    </div>
                    <p class="text-white-50 small mb-0 fs-6">Configure metadata, contact info, default formats, and academic sessions of the school.</p>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="<?php echo APP_URL; ?>/modules/administration/academic.php" class="btn btn-light btn-sm fw-bold shadow-sm rounded-3 text-primary px-3 py-2">
                    <i class="fa-solid fa-graduation-cap me-1"></i> Academic Sessions
                </a>
            </div>
        </div>
    </div>

    <!-- 3. KPI Summary Row -->
    <div class="row g-3 mb-4">
        <!-- School Name -->
        <div class="col-sm-6 col-lg-3">
            <div class="settings-kpi-card kpi-accent-blue">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="kpi-label-text">School Identity</span>
                    <div class="kpi-icon kpi-icon-blue">
                        <i class="fa-solid fa-school-flag"></i>
                    </div>
                </div>
                <div>
                    <div class="kpi-value-text text-truncate"><?php echo htmlspecialchars($settings['school_name']); ?></div>
                    <div class="small text-muted d-flex align-items-center gap-1">
                        <i class="fa-solid fa-id-card text-primary"></i>
                        <span><?php echo htmlspecialchars($settings['registration_number'] ?: 'REG-IGS'); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Academic Session -->
        <div class="col-sm-6 col-lg-3">
            <div class="settings-kpi-card kpi-accent-amber">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="kpi-label-text">Active Session</span>
                    <div class="kpi-icon kpi-icon-amber">
                        <i class="fa-solid fa-calendar-check"></i>
                    </div>
                </div>
                <div>
                    <div class="kpi-value-text"><?php echo htmlspecialchars($settings['current_academic_session']); ?></div>
                    <div class="small text-muted d-flex align-items-center gap-1">
                        <i class="fa-solid fa-clock text-warning"></i>
                        <span>Current Operational Session</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contact Info -->
        <div class="col-sm-6 col-lg-3">
            <div class="settings-kpi-card kpi-accent-green">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="kpi-label-text">Primary Phone</span>
                    <div class="kpi-icon kpi-icon-green">
                        <i class="fa-solid fa-phone"></i>
                    </div>
                </div>
                <div>
                    <div class="kpi-value-text text-truncate"><?php echo htmlspecialchars($settings['phone_number']); ?></div>
                    <div class="small text-muted d-flex align-items-center gap-1">
                        <i class="fa-solid fa-envelope text-success"></i>
                        <span class="text-truncate"><?php echo htmlspecialchars($settings['email']); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Format -->
        <div class="col-sm-6 col-lg-3">
            <div class="settings-kpi-card kpi-accent-purple">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="kpi-label-text">System Currency & Zone</span>
                    <div class="kpi-icon kpi-icon-purple">
                        <i class="fa-solid fa-globe"></i>
                    </div>
                </div>
                <div>
                    <div class="kpi-value-text"><?php echo htmlspecialchars($settings['currency']); ?> / <?php echo htmlspecialchars($settings['timezone']); ?></div>
                    <div class="small text-muted d-flex align-items-center gap-1">
                        <i class="fa-solid fa-sliders text-purple"></i>
                        <span>Format: <?php echo htmlspecialchars($settings['date_format']); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. Main Configuration Form & Sidebar Grid -->
    <div class="row g-4 mb-4">
        
        <!-- Left Side: Comprehensive Configuration Form -->
        <div class="col-lg-8">
            <div class="glass-panel">
                <div class="panel-header">
                    <h5 class="panel-title">
                        <i class="fa-solid fa-sliders text-primary me-1"></i>
                        School Information & System Metadata Profile
                    </h5>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-1 fw-bold">
                        Global Configurations
                    </span>
                </div>
                
                <div class="p-4">
                    <form id="schoolSettingsForm" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                        <input type="hidden" name="action" value="save_school_settings">
                        <input type="hidden" name="existing_logo" value="<?php echo htmlspecialchars($settings['school_logo'] ?? ''); ?>">

                        <!-- Section 1: Institution Profile & Metadata -->
                        <div class="section-label-header">
                            <i class="fa-solid fa-school me-1.5"></i> 1. Institution Metadata & Leadership Profile
                        </div>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-dark">School / Institution Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="school_name" value="<?php echo htmlspecialchars($settings['school_name']); ?>" required placeholder="e.g. Indus Grammar School">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-dark">Registration / License Number</label>
                                <input type="text" class="form-control" name="registration_number" value="<?php echo htmlspecialchars($settings['registration_number'] ?? ''); ?>" placeholder="e.g. REG-IGS-2026">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-dark">Owner / Board Director <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="owner_name" value="<?php echo htmlspecialchars($settings['owner_name']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-dark">Principal / Head of Institution <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="principal_name" value="<?php echo htmlspecialchars($settings['principal_name']); ?>" required>
                            </div>
                        </div>

                        <!-- Section 2: Contact Info & Communication Channels -->
                        <div class="section-label-header">
                            <i class="fa-solid fa-address-book me-1.5"></i> 2. Communication Channels & Official Contacts
                        </div>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-dark">Official Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($settings['email']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-dark">Primary Telephone Number <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="phone_number" value="<?php echo htmlspecialchars($settings['phone_number']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-dark">Official WhatsApp Helpline</label>
                                <input type="text" class="form-control" name="whatsapp_number" value="<?php echo htmlspecialchars($settings['whatsapp_number'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-dark">Official School Website URL</label>
                                <input type="text" class="form-control" name="website" value="<?php echo htmlspecialchars($settings['website'] ?? ''); ?>" placeholder="e.g. www.indus.edu.pk">
                            </div>
                        </div>

                        <!-- Section 3: Localization & Default Formats -->
                        <div class="section-label-header">
                            <i class="fa-solid fa-sliders me-1.5"></i> 3. System Formats, Currency & Academic Sessions
                        </div>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-dark">City / Campus Location <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="city" value="<?php echo htmlspecialchars($settings['city']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-dark">Current Academic Session <span class="text-danger">*</span></label>
                                <select class="form-select" name="current_academic_session" required>
                                    <?php
                                    try {
                                        $sessions = $db->query("SELECT session_name FROM academic_sessions ORDER BY session_name ASC")->fetchAll(PDO::FETCH_COLUMN);
                                        if (empty($sessions)) $sessions = ['2025-2026', '2026-2027', '2027-2028'];
                                        foreach ($sessions as $s) {
                                            $selected = ($settings['current_academic_session'] === $s) ? 'selected' : '';
                                            echo "<option value='$s' $selected>$s</option>";
                                        }
                                    } catch (Exception $e) {}
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-dark">System Timezone <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="timezone" value="<?php echo htmlspecialchars($settings['timezone']); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-dark">Currency Symbol <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="currency" value="<?php echo htmlspecialchars($settings['currency']); ?>" required placeholder="e.g. PKR">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-dark">Date Display Format <span class="text-danger">*</span></label>
                                <select class="form-select" name="date_format" required>
                                    <option value="Y-m-d" <?php echo ($settings['date_format'] === 'Y-m-d') ? 'selected' : ''; ?>>YYYY-MM-DD (e.g. <?php echo date('Y-m-d'); ?>)</option>
                                    <option value="d-m-Y" <?php echo ($settings['date_format'] === 'd-m-Y') ? 'selected' : ''; ?>>DD-MM-YYYY (e.g. <?php echo date('d-m-Y'); ?>)</option>
                                    <option value="m/d/Y" <?php echo ($settings['date_format'] === 'm/d/Y') ? 'selected' : ''; ?>>MM/DD/YYYY (e.g. <?php echo date('m/d/Y'); ?>)</option>
                                    <option value="d M Y" <?php echo ($settings['date_format'] === 'd M Y') ? 'selected' : ''; ?>>DD Mon YYYY (e.g. <?php echo date('d M Y'); ?>)</option>
                                </select>
                            </div>
                        </div>

                        <!-- Section 4: Address & Logo Branding -->
                        <div class="section-label-header">
                            <i class="fa-solid fa-map-location-dot me-1.5"></i> 4. Postal Address & Official Insignia Logo
                        </div>
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-dark">Complete School Postal Address <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="school_address" rows="3" required><?php echo htmlspecialchars($settings['school_address']); ?></textarea>
                        </div>

                        <div class="row g-3 align-items-center mb-4 p-3 bg-light rounded-3 border">
                            <div class="col-md-8">
                                <label class="form-label small fw-bold text-dark">Upload Official School Logo</label>
                                <input type="file" class="form-control" name="school_logo" accept="image/*">
                                <div class="form-text small text-muted">PNG or JPG formats recommended (Max 2MB).</div>
                            </div>
                            <div class="col-md-4 text-center">
                                <?php if (!empty($settings['school_logo'])): ?>
                                    <div class="p-2 border rounded bg-white shadow-sm d-inline-block">
                                        <img src="<?php echo APP_URL . '/' . $settings['school_logo']; ?>" alt="Logo Preview" style="max-height: 70px; max-width: 100%;">
                                    </div>
                                <?php else: ?>
                                    <div class="p-3 border rounded bg-white text-muted small">
                                        <i class="fa-solid fa-image fs-3 d-block mb-1 text-muted"></i> No Logo Uploaded
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="d-flex align-items-center justify-content-end gap-2 border-top pt-4">
                            <button type="reset" class="btn btn-outline-secondary px-4 fw-semibold">Reset Form</button>
                            <button type="submit" class="btn btn-primary px-5 fw-bold shadow-sm" id="btnSaveSettings">
                                <i class="fa-solid fa-floppy-disk me-1.5"></i> Save Settings Configuration
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right Side: Administration Gateways & Health Status -->
        <div class="col-lg-4">
            
            <!-- Gateway Control Box -->
            <div class="glass-panel mb-4">
                <div class="panel-header">
                    <h5 class="panel-title">
                        <i class="fa-solid fa-circle-nodes text-primary me-1"></i>
                        Administration Gateways
                    </h5>
                </div>
                <div class="p-3">
                    <div class="d-flex flex-column gap-2.5">
                        <a href="<?php echo APP_URL; ?>/modules/administration/users.php" class="sidebar-gateway-item">
                            <div class="sidebar-gateway-icon">
                                <i class="fa-solid fa-users-gear"></i>
                            </div>
                            <div class="d-flex align-items-center justify-content-between flex-grow-1">
                                <span>User Management</span>
                                <i class="fa-solid fa-chevron-right small text-muted"></i>
                            </div>
                        </a>

                        <a href="<?php echo APP_URL; ?>/modules/administration/roles.php" class="sidebar-gateway-item">
                            <div class="sidebar-gateway-icon" style="background-color: #fee2e2; color: var(--danger-color);">
                                <i class="fa-solid fa-user-shield"></i>
                            </div>
                            <div class="d-flex align-items-center justify-content-between flex-grow-1">
                                <span>Roles & Permissions</span>
                                <i class="fa-solid fa-chevron-right small text-muted"></i>
                            </div>
                        </a>

                        <a href="<?php echo APP_URL; ?>/modules/administration/academic.php" class="sidebar-gateway-item">
                            <div class="sidebar-gateway-icon" style="background-color: #fffbeb; color: var(--warning-color);">
                                <i class="fa-solid fa-graduation-cap"></i>
                            </div>
                            <div class="d-flex align-items-center justify-content-between flex-grow-1">
                                <span>Academic Setup</span>
                                <i class="fa-solid fa-chevron-right small text-muted"></i>
                            </div>
                        </a>

                        <a href="<?php echo APP_URL; ?>/modules/administration/backup.php" class="sidebar-gateway-item">
                            <div class="sidebar-gateway-icon" style="background-color: #e0f2fe; color: #0284c7;">
                                <i class="fa-solid fa-database"></i>
                            </div>
                            <div class="d-flex align-items-center justify-content-between flex-grow-1">
                                <span>Database Backup</span>
                                <i class="fa-solid fa-chevron-right small text-muted"></i>
                            </div>
                        </a>

                        <a href="<?php echo APP_URL; ?>/modules/administration/logs.php" class="sidebar-gateway-item">
                            <div class="sidebar-gateway-icon" style="background-color: #f3e8ff; color: var(--purple-color);">
                                <i class="fa-solid fa-clipboard-list"></i>
                            </div>
                            <div class="d-flex align-items-center justify-content-between flex-grow-1">
                                <span>Security Audit Logs</span>
                                <i class="fa-solid fa-chevron-right small text-muted"></i>
                            </div>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Health Status Card -->
            <div class="glass-panel p-4 text-center border-0" style="background: linear-gradient(135deg, #ffffff, #f0fdf4);">
                <div class="d-inline-flex p-3 rounded-circle bg-success-subtle text-success mb-3">
                    <i class="fa-solid fa-circle-check fs-2"></i>
                </div>
                <h5 class="fw-bold text-success mb-1">Configurations Operational</h5>
                <p class="small text-muted mb-3">All school metadata and academic constants are active and verified in database.</p>
                <span class="badge bg-success rounded-pill px-4 py-2 fw-semibold">
                    <i class="fa-solid fa-shield-halved me-1"></i> Active System State
                </span>
            </div>

        </div>

    </div>

</div>

<!-- Toast Notification Container -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="settingsToast" class="toast align-items-center text-white border-0 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="settingsToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
function showToast(msg, ok) {
    const t = document.getElementById("settingsToast");
    const m = document.getElementById("settingsToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

document.addEventListener("DOMContentLoaded", function() {
    const form = document.getElementById("schoolSettingsForm");
    if (form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            const btn = document.getElementById("btnSaveSettings");
            btn.disabled = true; 
            btn.innerHTML = "<i class=\"fa-solid fa-spinner fa-spin me-1.5\"></i> Saving Configurations...";
            
            fetch("../../ajax/admin.php", { method: "POST", body: new FormData(form) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) {
                        setTimeout(() => location.reload(), 900);
                    } else { 
                        btn.disabled = false; 
                        btn.innerHTML = "<i class=\"fa-solid fa-floppy-disk me-1.5\"></i> Save Settings Configuration"; 
                    }
                })
                .catch(() => { 
                    showToast("Network connection error.", false); 
                    btn.disabled = false; 
                    btn.innerHTML = "<i class=\"fa-solid fa-floppy-disk me-1.5\"></i> Save Settings Configuration"; 
                });
        });
    }
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
