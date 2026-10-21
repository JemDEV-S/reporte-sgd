param(
    [string]$PhpExecutable = 'php'
)

$ErrorActionPreference = 'Stop'
$applicationPath = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path
$artisanPath = Join-Path $applicationPath 'artisan'
$phpPath = (Get-Command $PhpExecutable -ErrorAction Stop).Source
$hashProvider = [System.Security.Cryptography.SHA256]::Create()
try {
    $pathHash = [System.BitConverter]::ToString($hashProvider.ComputeHash([System.Text.Encoding]::UTF8.GetBytes($applicationPath.ToLowerInvariant()))).Replace('-', '')
} finally {
    $hashProvider.Dispose()
}
$workerMutex = New-Object System.Threading.Mutex($false, "Local\SIRMD-SGD-Worker-$pathHash")
$ownsMutex = $false
try {
    try { $ownsMutex = $workerMutex.WaitOne(0) } catch [System.Threading.AbandonedMutexException] { $ownsMutex = $true }
    if (-not $ownsMutex) { exit 0 }
    Set-Location -LiteralPath $applicationPath
    while ($true) {
        # Laravel puede finalizar por reinicio solicitado, memoria o fallo del servicio.
        try {
            & $phpPath $artisanPath queue:work ingesta --queue=sgd --sleep=3 --timeout=3600 --tries=0 --memory=256 --no-interaction
        } catch {
            Write-Warning 'El procesador terminó con un error. Se reiniciará en cinco segundos.'
        }
        Start-Sleep -Seconds 5
    }
} finally {
    if ($ownsMutex) { $workerMutex.ReleaseMutex() }
    $workerMutex.Dispose()
}
