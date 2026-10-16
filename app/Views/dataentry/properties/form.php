<?php
/**
 * @var array|null $property
 * @var array $schemes    eligible schemes (create)
 * @var array $sectors    active sectors of the property's scheme (edit)
 * @var array|null $roleUsers
 */
use App\Core\Lookups;

$isEdit = $property !== null;
$action = $isEdit ? '/data-entry/properties/' . $property['property_id'] : '/data-entry/properties';
$val = static fn (string $k, mixed $default = '') => old($k, $property[$k] ?? $default);
$schemeId = (string) $val('scheme_id');
$sectorId = (string) $val('sector_id');
$confirm = (string) old('confirm_location', '');
?>
<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fa-solid fa-building-circle-check" aria-hidden="true"></i> <?= $isEdit ? 'Edit Property' : 'Add Property' ?></h2>
        <p class="page-subtitle"><?= $isEdit
            ? e($property['property_code'] . ' · ' . $property['scheme_name'] . ' · status Listed')
            : 'Step 1 select scheme → Step 2 select sector → Step 3 confirm → plot details.' ?></p>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('/data-entry/properties')) ?>" class="btn btn-light"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> <span>Back to list</span></a>
    </div>
</div>

<?php if (!$isEdit && $schemes === []): ?>
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <i class="fa-solid fa-map-location-dot fa-bounce empty-icon" aria-hidden="true"></i>
                <p class="empty-title">No scheme is ready for property entry</p>
                <p class="text-muted mb-3">A scheme appears here only when it is active and has at least one active sector.</p>
                <a href="<?= e(url('/data-entry/schemes/create')) ?>" class="btn btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> <span>Create Scheme</span></a>
            </div>
        </div>
    </div>
<?php else: ?>

<form method="post" action="<?= e(url($action)) ?>" novalidate data-validate data-property-form>
    <?= csrf_field() ?>
    <div class="card">
        <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-location-dot" aria-hidden="true"></i> Location</h3></div>
        <div class="card-body">
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label" for="scheme_id"><span class="step-no">1</span> Scheme <span class="required">*</span></label>
                    <?php if ($isEdit): ?>
                        <input type="text" class="form-control" value="<?= e($property['scheme_name'] . ' (' . $property['scheme_code'] . ')') ?>" readonly id="scheme_id" data-scheme-label="<?= e($property['scheme_name']) ?>">
                        <div class="form-hint">Scheme cannot be changed after creation (role users were assigned from it).</div>
                    <?php else: ?>
                        <select id="scheme_id" name="scheme_id" class="form-select<?= invalid('scheme_id') ?>" data-rules="required" data-label="Scheme"
                                data-sector-source="/data-entry/schemes/{id}/sectors" data-sector-target="sector_id">
                            <option value="">Select scheme</option>
                            <?php foreach ($schemes as $s): ?>
                                <option value="<?= (int) $s['scheme_id'] ?>"<?= (string) $s['scheme_id'] === $schemeId ? ' selected' : '' ?>><?= e($s['scheme_name'] . ' (' . $s['scheme_code'] . ')') ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-hint">Only schemes with at least one active sector are listed.</div>
                    <?php endif; ?>
                    <div class="invalid-feedback"><?= e(error('scheme_id')) ?></div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="sector_id"><span class="step-no">2</span> Sector <span class="required">*</span></label>
                    <select id="sector_id" name="sector_id" class="form-select<?= invalid('sector_id') ?>" data-rules="required" data-label="Sector"
                            data-selected="<?= e($sectorId) ?>"<?= !$isEdit ? ' disabled' : '' ?>>
                        <?php if ($isEdit): ?>
                            <option value="">Select sector</option>
                            <?php foreach ($sectors as $s): ?>
                                <option value="<?= (int) $s['sector_id'] ?>"<?= (string) $s['sector_id'] === $sectorId ? ' selected' : '' ?>><?= e($s['sector_name']) ?></option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="">Select scheme first</option>
                        <?php endif; ?>
                    </select>
                    <div class="form-hint" data-sector-hint>Sectors load from the selected scheme.</div>
                    <div class="invalid-feedback"><?= e(error('sector_id')) ?></div>
                </div>
            </div>

            <div class="form-group confirm-box<?= error('confirm_location') !== '' ? ' has-error' : '' ?>" data-confirm-box>
                <p class="confirm-question">
                    <span class="step-no">3</span>
                    Is property ko Scheme <strong data-confirm-scheme>[—]</strong> ke Sector <strong data-confirm-sector>[—]</strong> me create karna hai?
                </p>
                <div class="radio-row">
                    <label class="check"><input type="radio" name="confirm_location" value="YES"<?= $confirm === 'YES' ? ' checked' : '' ?> data-rules="required" data-label="the confirmation (Yes / No)"> <span><strong>Yes</strong>, sahi hai</span></label>
                    <label class="check"><input type="radio" name="confirm_location" value="NO"<?= $confirm === 'NO' ? ' checked' : '' ?>> <span><strong>No</strong>, badalna hai</span></label>
                </div>
                <div class="invalid-feedback"><?= e(error('confirm_location')) ?></div>
                <p class="form-hint" data-confirm-no hidden><i class="fa-solid fa-circle-info" aria-hidden="true"></i> Please change the scheme / sector above. Save stays disabled until you answer <strong>Yes</strong>.</p>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-ruler-combined" aria-hidden="true"></i> Plot details</h3></div>
        <div class="card-body">
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label" for="plot_no">Plot no. <span class="required">*</span></label>
                    <input type="text" id="plot_no" name="plot_no" class="form-control<?= invalid('plot_no') ?>" maxlength="30" value="<?= e($val('plot_no')) ?>"
                           placeholder="e.g. 112-A" data-uppercase data-rules="required|plotno" data-label="Plot no.">
                    <div class="invalid-feedback"><?= e(error('plot_no')) ?></div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="property_type">Plot type <span class="required">*</span></label>
                    <select id="property_type" name="property_type" class="form-select<?= invalid('property_type') ?>" data-rules="required" data-label="Plot type">
                        <?= Lookups::options(Lookups::PROPERTY_TYPES, (string) $val('property_type'), 'Select type') ?>
                    </select>
                    <div class="invalid-feedback"><?= e(error('property_type')) ?></div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="area_value">Area <span class="required">*</span></label>
                    <input type="text" id="area_value" name="area_value" class="form-control<?= invalid('area_value') ?>" inputmode="decimal" maxlength="13"
                           value="<?= e($val('area_value')) ?>" placeholder="e.g. 150.50" data-rules="required|decimal:2|positive" data-label="Area" data-area-value>
                    <div class="invalid-feedback"><?= e(error('area_value')) ?></div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="area_unit">Area unit <span class="required">*</span></label>
                    <select id="area_unit" name="area_unit" class="form-select<?= invalid('area_unit') ?>" data-rules="required" data-label="Area unit" data-area-unit>
                        <?= Lookups::options(Lookups::AREA_UNITS, (string) $val('area_unit', 'SQM'), 'Select unit') ?>
                    </select>
                    <div class="form-hint" data-area-preview>1 sq ft = 0.092903 sq m</div>
                    <div class="invalid-feedback"><?= e(error('area_unit')) ?></div>
                </div>
                <div class="form-group span-2">
                    <label class="form-label" for="remarks">Remarks</label>
                    <textarea id="remarks" name="remarks" class="form-control<?= invalid('remarks') ?>" maxlength="500" rows="2"
                              placeholder="Optional notes" data-rules="max:500|text" data-label="Remarks"><?= e($val('remarks')) ?></textarea>
                    <div class="invalid-feedback"><?= e(error('remarks')) ?></div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($isEdit && !empty($roleUsers)): ?>
        <div class="card">
            <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-user-tie" aria-hidden="true"></i> Assigned role users (snapshot)</h3></div>
            <div class="card-body">
                <div class="chip-list">
                    <?php foreach ($roleUsers as $ru): ?>
                        <span class="chip"><strong><?= e($ru['role_name']) ?>:</strong> <?= e($ru['name']) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary btn-lg" data-loading-text="Saving…" data-requires-yes>
            <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> <span><?= $isEdit ? 'Update Property' : 'Save Property' ?></span>
        </button>
        <?php if (!$isEdit): ?>
            <button type="submit" name="save_and_new" value="1" class="btn btn-gold btn-lg" data-loading-text="Saving…" data-requires-yes>
                <i class="fa-solid fa-plus" aria-hidden="true"></i> <span>Save &amp; add another</span>
            </button>
        <?php endif; ?>
        <a href="<?= e(url('/data-entry/properties')) ?>" class="btn btn-light btn-lg"><i class="fa-solid fa-xmark" aria-hidden="true"></i> <span>Cancel</span></a>
    </div>
</form>
<?php endif; ?>
