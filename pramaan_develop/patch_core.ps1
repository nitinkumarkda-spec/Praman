$file = "$PSScriptRoot\public\assets\js\core.js"
$content = [System.IO.File]::ReadAllText($file)
$oldCode = @"
            if (Auth.can('scheme.view', 'property.view')) {
                return '/data-entry/dashboard';
            }
            if (Auth.can('verification.view')) {
                return '/oic/dashboard';
            }
            if (Number(u.role_id) > 1 && !Auth.can('scheme.view', 'property.view', 'verification.view')) {
                return '/role/dashboard';
            }
"@
$newCode = @"
            const roleId = Number(u.role_id);
            if (roleId === 7 || Auth.can('verification.send')) {
                return '/oic/dashboard';
            }
            const rObj = roleId ? DB.find('roles', roleId) : null;
            if (roleId > 1 && rObj && rObj.status_id) {
                return '/role/dashboard';
            }
            if (roleId === 6 || (Auth.can('scheme.view') && Auth.can('property.create', 'scheme.create'))) {
                return '/data-entry/dashboard';
            }
            if (roleId > 1) {
                return '/role/dashboard';
            }
"@

$oldNorm = $oldCode.Replace("`r`n", "`n")
$newNorm = $newCode.Replace("`r`n", "`n")
$contentNorm = $content.Replace("`r`n", "`n")

if ($contentNorm.Contains($oldNorm)) {
    $contentNorm = $contentNorm.Replace($oldNorm, $newNorm)
    [System.IO.File]::WriteAllText($file, $contentNorm)
    Write-Host "SUCCESS: Replaced code in core.js"
} else {
    Write-Host "ERROR: oldCode not found in core.js"
}

