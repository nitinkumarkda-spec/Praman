<?php
$cssPath = __DIR__ . '/../public/assets/css/components.css';
$css = file_get_contents($cssPath);

$newCss = <<<CSS

/* =====================================================================
   Custom Report Builder & Guide Styles
   ===================================================================== */
.report-guide-card {
    background: #ffffff;
    border: 1px solid var(--border);
    border-left: 4px solid var(--navy-800);
    border-radius: var(--radius);
    box-shadow: var(--shadow-sm);
    margin-bottom: 20px;
    overflow: hidden;
    transition: all .2s ease;
}
.report-guide-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding: 14px 18px;
    background: var(--navy-50);
    border-bottom: 1px solid var(--border);
    flex-wrap: wrap;
}
.report-guide-title-box {
    display: flex;
    align-items: center;
    gap: 12px;
}
.report-guide-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: var(--navy-800);
    color: #fff;
    display: grid;
    place-items: center;
    font-size: 15px;
    flex-shrink: 0;
}
.report-guide-title {
    margin: 0;
    font-size: 15px;
    font-weight: 700;
    color: var(--navy-900);
}
.report-guide-subtitle {
    margin: 2px 0 0;
    font-size: 12px;
    color: var(--text-muted);
}
.report-guide-body {
    padding: 16px 18px;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 14px;
    background: #ffffff;
}
.report-guide-step-card {
    background: #ffffff;
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 14px 16px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,.03);
    transition: border-color .15s ease, transform .15s ease;
}
.report-guide-step-card:hover {
    border-color: var(--navy-600);
    transform: translateY(-2px);
}
.report-guide-step-card-head {
    display: flex;
    align-items: center;
    gap: 8px;
}
.guide-step-badge {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: var(--navy-800);
    color: #fff;
    display: grid;
    place-items: center;
    font-size: 11.5px;
    font-weight: 700;
    flex-shrink: 0;
}
.guide-step-title {
    font-size: 13.5px;
    font-weight: 700;
    color: var(--navy-900);
    margin: 0;
}
.guide-step-desc {
    font-size: 12px;
    color: var(--text-muted);
    margin: 0;
    line-height: 1.45;
}
.guide-step-list {
    list-style: none;
    padding: 0;
    margin: 4px 0 0;
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.guide-step-list li {
    font-size: 12px;
    color: var(--slate-600);
    display: flex;
    align-items: flex-start;
    gap: 6px;
    line-height: 1.4;
}
.guide-step-list li i {
    color: var(--green-600);
    margin-top: 3px;
    font-size: 11px;
    flex-shrink: 0;
}
.guide-step-list li strong {
    color: var(--navy-900);
}

/* Wizard card & column pills */
.report-wizard-card {
    border: 1px solid var(--border);
    border-radius: var(--radius);
    box-shadow: var(--shadow-sm);
}
.wizard-step-section {
    margin-bottom: 20px;
}
.wizard-step-section:last-child {
    margin-bottom: 0;
}
.wizard-step-bar {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 12px;
    padding-bottom: 8px;
    border-bottom: 1px dashed var(--border);
    flex-wrap: wrap;
}
.wizard-step-num {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
    padding: 3px 10px;
    border-radius: 999px;
    background: var(--navy-100);
    color: var(--navy-800);
}
.wizard-step-name {
    font-size: 14px;
    font-weight: 700;
    color: var(--navy-900);
}
.wizard-step-hint {
    font-size: 12px;
    color: var(--text-muted);
    margin-left: auto;
}

.custom-cols-box {
    background: var(--navy-50);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 14px 16px;
}
.custom-cols-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 12px;
}
.custom-cols-legend {
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: var(--navy-800);
    display: flex;
    align-items: center;
    gap: 6px;
}
.custom-cols-actions {
    display: flex;
    gap: 6px;
    align-items: center;
    flex-wrap: wrap;
}
.custom-cols-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.col-pill-label {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 6px 12px;
    background: #ffffff;
    border: 1.5px solid var(--border);
    border-radius: 8px;
    font-size: 12.5px;
    font-weight: 600;
    color: var(--slate-600);
    cursor: pointer;
    transition: all .15s ease;
    user-select: none;
}
.col-pill-label:hover {
    border-color: var(--navy-600);
    color: var(--navy-900);
    background: #f8fafc;
}
.col-pill-label.active {
    background: #ffffff;
    border-color: var(--navy-700);
    color: var(--navy-900);
    box-shadow: 0 1px 3px rgba(15, 43, 82, .12);
}
.col-pill-label input[type="checkbox"] {
    accent-color: var(--navy-800);
    cursor: pointer;
}
.col-pill-label i {
    font-size: 12px;
    color: var(--navy-600);
}

.report-summary-bar {
    display: grid !important;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)) !important;
    gap: 14px !important;
    background: linear-gradient(135deg, #ffffff 0%, var(--navy-50) 100%) !important;
    border: 1px solid var(--border) !important;
    border-radius: var(--radius) !important;
    padding: 14px 18px !important;
    margin-bottom: 18px !important;
    box-shadow: var(--shadow-sm) !important;
}
.report-summary-item {
    display: flex !important;
    flex-direction: row !important;
    align-items: center !important;
    gap: 12px !important;
    padding: 8px 12px !important;
    background: #ffffff !important;
    border: 1px solid var(--border) !important;
    border-radius: var(--radius-sm) !important;
}
.report-summary-icon {
    width: 38px;
    height: 38px;
    border-radius: 8px;
    display: grid;
    place-items: center;
    font-size: 16px;
    flex-shrink: 0;
}
.report-summary-icon.icon-navy { background: var(--navy-100); color: var(--navy-800); }
.report-summary-icon.icon-gold { background: var(--gold-100); color: var(--gold-600); }
.report-summary-icon.icon-green { background: var(--green-100); color: var(--green-600); }
.report-summary-icon.icon-blue { background: var(--blue-100); color: var(--blue-600); }

.report-summary-meta {
    display: flex;
    flex-direction: column;
}
.report-summary-label {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .03em;
    color: var(--text-muted);
    font-weight: 600;
}
.report-summary-val {
    font-size: 18px;
    font-weight: 700;
    color: var(--navy-900);
    line-height: 1.2;
}
CSS;

if (strpos($css, 'Custom Report Builder & Guide Styles') === false) {
    file_put_contents($cssPath, $css . "\n" . $newCss);
    echo "CSS updated successfully.\n";
} else {
    echo "CSS already present.\n";
}

