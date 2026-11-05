$content = [System.IO.File]::ReadAllText("$PSScriptRoot\public\assets\js\core.js")
$content = $content.Replace("`r`n", "`n")
[System.IO.File]::WriteAllText("$PSScriptRoot\public\assets\js\core.js", $content)
Write-Host "Normalized line endings"

