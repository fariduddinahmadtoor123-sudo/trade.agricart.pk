# Locates Python, installs the MT5 bridge packages, and starts mt5_bridge.py.
param(
    [switch]$Detach
)

$ErrorActionPreference = "Stop"
$BridgeDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$Root = (Resolve-Path (Join-Path $BridgeDir "..\..\..")).Path
$EnvFile = Join-Path $Root ".env"
$VenvDir = Join-Path $env:LOCALAPPDATA "agricart-mt5-bridge\venv"
$LogFile = Join-Path $BridgeDir "bridge.log"
$PidFile = Join-Path $BridgeDir "bridge.pid"

function Write-Step([string]$Message) {
    Write-Host $Message
}

function Test-RealPython([string]$Executable) {
    if ([string]::IsNullOrWhiteSpace($Executable)) {
        return $false
    }

    if ($Executable -like "*\WindowsApps\*") {
        return $false
    }

    if (-not (Test-Path -LiteralPath $Executable)) {
        return $false
    }

    & $Executable -c "import sys; raise SystemExit(0 if sys.version_info >= (3, 10) else 1)" *> $null
    return $LASTEXITCODE -eq 0
}

function Find-Python {
    $candidates = New-Object System.Collections.Generic.List[string]

    foreach ($name in @("py", "python", "python3")) {
        $command = Get-Command $name -ErrorAction SilentlyContinue
        if ($command) {
            $candidates.Add($command.Source)
        }
    }

    $py = Get-Command py -ErrorAction SilentlyContinue
    if ($py) {
        $fromLauncher = & py -3 -c "import sys; print(sys.executable)" 2>$null
        if ($fromLauncher) {
            $candidates.Add($fromLauncher.Trim())
        }
    }

    $roots = @(
        (Join-Path $env:LOCALAPPDATA "Programs\Python"),
        "C:\Python312",
        "C:\Python313",
        "C:\Python311",
        "C:\Program Files\Python312",
        "C:\Program Files\Python313",
        "C:\Program Files\Python311",
        "C:\laragon\bin\python"
    )

    foreach ($root in $roots) {
        if (-not (Test-Path -LiteralPath $root)) {
            continue
        }

        if ((Split-Path -Leaf $root) -like "Python*") {
            $direct = Join-Path $root "python.exe"
            if (Test-Path -LiteralPath $direct) {
                $candidates.Add($direct)
            }
        }

        Get-ChildItem -LiteralPath $root -Filter python.exe -Recurse -Depth 2 -ErrorAction SilentlyContinue |
            ForEach-Object { $candidates.Add($_.FullName) }
    }

    foreach ($hive in @("HKCU:\Software\Python\PythonCore", "HKLM:\Software\Python\PythonCore")) {
        if (-not (Test-Path $hive)) {
            continue
        }

        Get-ChildItem $hive -ErrorAction SilentlyContinue | ForEach-Object {
            $install = (Get-ItemProperty -Path (Join-Path $_.PSPath "InstallPath") -ErrorAction SilentlyContinue)."(default)"
            if ($install) {
                $candidates.Add((Join-Path $install "python.exe"))
            }
        }
    }

    foreach ($candidate in $candidates) {
        if (Test-RealPython $candidate) {
            return (Resolve-Path -LiteralPath $candidate).Path
        }
    }

    return $null
}

function Install-Python {
    $winget = Get-Command winget -ErrorAction SilentlyContinue
    if (-not $winget) {
        throw "Python was not found, and winget is not available to install it."
    }

    Write-Step "Python was not found. Installing Python 3.12 for this user."
    & winget install -e --id Python.Python.3.12 --scope user --accept-package-agreements --accept-source-agreements --disable-interactivity
    if ($LASTEXITCODE -ne 0 -and $LASTEXITCODE -ne -1978335189) {
        throw "winget could not install Python. Exit code $LASTEXITCODE."
    }

    $env:Path = [System.Environment]::GetEnvironmentVariable("Path", "Machine") + ";" + [System.Environment]::GetEnvironmentVariable("Path", "User")
}

function Read-EnvValue([string]$Text, [string]$Key) {
    foreach ($line in ($Text -split "\r?\n")) {
        if ($line -match "^\s*$([regex]::Escape($Key))=(.*)$") {
            $value = $Matches[1].Trim()
            if ($value.StartsWith('"') -and $value.EndsWith('"') -and $value.Length -ge 2) {
                return $value.Substring(1, $value.Length - 2)
            }

            return $value
        }
    }

    return ""
}

function Set-EnvValue([string]$Path, [string]$Key, [string]$Value) {
    $text = ""
    if (Test-Path -LiteralPath $Path) {
        $text = [System.IO.File]::ReadAllText($Path)
    }

    $escaped = [regex]::Escape($Key)
    $quoted = '"' + ($Value -replace '"', '\"') + '"'
    $line = "$Key=$quoted"
    if ($text -match "(?m)^\s*$escaped=") {
        $text = [regex]::Replace($text, "(?m)^\s*$escaped=.*$", $line)
    } else {
        if ($text.Length -gt 0 -and -not $text.EndsWith("`n")) {
            $text += "`r`n"
        }
        $text += $line + "`r`n"
    }

    $utf8 = New-Object System.Text.UTF8Encoding $false
    [System.IO.File]::WriteAllText($Path, $text, $utf8)
}

function Find-Php {
    $command = Get-Command php -ErrorAction SilentlyContinue
    if ($command -and (Test-Path -LiteralPath $command.Source)) {
        return $command.Source
    }

    $root = "C:\laragon\bin\php"
    if (-not (Test-Path -LiteralPath $root)) {
        return $null
    }

    $found = Get-ChildItem -LiteralPath $root -Directory -ErrorAction SilentlyContinue |
        Sort-Object Name -Descending |
        ForEach-Object { Join-Path $_.FullName "php.exe" } |
        Where-Object { Test-Path -LiteralPath $_ } |
        Select-Object -First 1

    return $found
}

function Find-AppUrl([string]$EnvText) {
    $configured = Read-EnvValue $EnvText "APP_URL"
    $candidates = @($configured, "http://all.agricart.pk.test", "http://localhost", "http://127.0.0.1:8000") |
        Where-Object { -not [string]::IsNullOrWhiteSpace($_) } |
        Select-Object -Unique

    foreach ($candidate in $candidates) {
        try {
            $response = Invoke-WebRequest -Uri ($candidate.TrimEnd("/") + "/up") -UseBasicParsing -TimeoutSec 4
            if ($response.StatusCode -ge 200 -and $response.StatusCode -lt 400) {
                return $candidate.TrimEnd("/")
            }
        } catch {
            continue
        }
    }

    if (-not [string]::IsNullOrWhiteSpace($configured)) {
        return $configured.TrimEnd("/")
    }

    return "http://127.0.0.1:8000"
}

function Find-Mt5Terminal {
    $candidates = @(
        "C:\Program Files\MetaTrader 5\terminal64.exe",
        "C:\Program Files (x86)\MetaTrader 5\terminal64.exe",
        (Join-Path $env:PROGRAMFILES "MetaTrader 5\terminal64.exe")
    )

    foreach ($candidate in $candidates) {
        if ($candidate -and (Test-Path -LiteralPath $candidate)) {
            return $candidate
        }
    }

    return ""
}

function New-BridgeToken {
    $bytes = New-Object byte[] 32
    $rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    $rng.GetBytes($bytes)
    return ([Convert]::ToBase64String($bytes)).TrimEnd("=").Replace("+", "-").Replace("/", "_")
}

$python = Find-Python
if (-not $python) {
    Install-Python
    $python = Find-Python
}

if (-not $python) {
    throw "Python is still not available after the installer finished. Open a new terminal and run this script again."
}

Write-Step "Using Python: $python"

$venvPython = Join-Path $VenvDir "Scripts\python.exe"
if (-not (Test-RealPython $venvPython)) {
    Write-Step "Creating a private environment for the bridge."
    if (Test-Path -LiteralPath $VenvDir) {
        Remove-Item -LiteralPath $VenvDir -Recurse -Force
    }
    & $python -m venv $VenvDir
    if ($LASTEXITCODE -ne 0) {
        throw "Could not create the Python environment."
    }
}

Write-Step "Installing bridge packages."
& $venvPython -m pip install --disable-pip-version-check -r (Join-Path $BridgeDir "requirements.txt")
if ($LASTEXITCODE -ne 0) {
    throw "Could not install the packages in requirements.txt."
}

& $venvPython -c "import numpy, MetaTrader5" *> $null
if ($LASTEXITCODE -ne 0) {
    Write-Step "The newest NumPy build is blocked on this PC. Installing NumPy 2.2.6."
    & $venvPython -m pip install --disable-pip-version-check "numpy==2.2.6"
    & $venvPython -c "import numpy, MetaTrader5" *> $null
    if ($LASTEXITCODE -ne 0) {
        throw "Python can see the packages, but Windows blocked the MT5 library. Allow Python in Windows Security, then run this script again."
    }
}

if (-not (Test-Path -LiteralPath $EnvFile)) {
    throw "The project .env file is missing, so the bridge cannot share its token with the website."
}

$envText = [System.IO.File]::ReadAllText($EnvFile)
$bridgeToken = Read-EnvValue $envText "TRADING_MT5_BRIDGE_TOKEN"
if ([string]::IsNullOrWhiteSpace($bridgeToken)) {
    $bridgeToken = New-BridgeToken
    Set-EnvValue $EnvFile "TRADING_MT5_BRIDGE_TOKEN" $bridgeToken
    Write-Step "Saved a bridge token in .env."
    $envText = [System.IO.File]::ReadAllText($EnvFile)
}

$bridgeUrl = Read-EnvValue $envText "TRADING_MT5_BRIDGE_URL"
if ([string]::IsNullOrWhiteSpace($bridgeUrl)) {
    Set-EnvValue $EnvFile "TRADING_MT5_BRIDGE_URL" "http://127.0.0.1:8765"
}

$laravelToken = Read-EnvValue $envText "TRADING_MT5_LARAVEL_TOKEN"
if ([string]::IsNullOrWhiteSpace($laravelToken)) {
    $php = Find-Php
    if (-not $php) {
        throw "PHP was not found, so a website token for the bridge could not be created."
    }

    $tokenFile = Join-Path $Root "storage\app\mt5-laravel-token.txt"
    if (Test-Path -LiteralPath $tokenFile) {
        Remove-Item -LiteralPath $tokenFile -Force
    }

    $phpCode = @'
$u = App\Models\User::query()->orderBy('id')->first();
if ($u === null) {
    fwrite(STDERR, "NO_USER");
    exit(1);
}
file_put_contents(storage_path('app/mt5-laravel-token.txt'), $u->createToken('mt5-bridge')->plainTextToken);
'@
    $encoded = [Convert]::ToBase64String([System.Text.Encoding]::UTF8.GetBytes($phpCode))
    $execute = "eval(base64_decode('$encoded'));"
    & $php (Join-Path $Root "artisan") tinker --execute $execute
    if ($LASTEXITCODE -ne 0 -or -not (Test-Path -LiteralPath $tokenFile)) {
        throw "Could not create a website token. Add a user, then run this script again."
    }

    $laravelToken = ([System.IO.File]::ReadAllText($tokenFile)).Trim()
    Remove-Item -LiteralPath $tokenFile -Force
    if ([string]::IsNullOrWhiteSpace($laravelToken)) {
        throw "The website token was empty."
    }

    Set-EnvValue $EnvFile "TRADING_MT5_LARAVEL_TOKEN" $laravelToken
    Write-Step "Saved a website token in .env."
}

$appUrl = Find-AppUrl $envText
$mt5 = Find-Mt5Terminal

function Read-SharedText([string]$Path) {
    if (-not (Test-Path -LiteralPath $Path)) {
        return ""
    }

    $stream = [System.IO.File]::Open($Path, [System.IO.FileMode]::Open, [System.IO.FileAccess]::Read, [System.IO.FileShare]::ReadWrite)
    $reader = New-Object System.IO.StreamReader($stream)
    try {
        return $reader.ReadToEnd()
    } finally {
        $reader.Close()
    }
}

function Stop-OurBridge {
    Get-CimInstance Win32_Process -Filter "Name = 'python.exe'" -ErrorAction SilentlyContinue |
        Where-Object { $_.CommandLine -like "*mt5_bridge.py*" } |
        ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }

    $deadline = (Get-Date).AddSeconds(8)
    do {
        $busy = Get-NetTCPConnection -LocalPort 8765 -State Listen -ErrorAction SilentlyContinue | Select-Object -First 1
        if (-not $busy) {
            return
        }

        $owner = Get-Process -Id $busy.OwningProcess -ErrorAction SilentlyContinue
        if ($owner -and $owner.ProcessName -eq "python") {
            Stop-Process -Id $owner.Id -Force -ErrorAction SilentlyContinue
        }

        Start-Sleep -Milliseconds 400
    } while ((Get-Date) -lt $deadline)
}

$listener = Get-NetTCPConnection -LocalPort 8765 -State Listen -ErrorAction SilentlyContinue | Select-Object -First 1
if ($listener) {
    try {
        $health = Invoke-WebRequest -Uri "http://127.0.0.1:8765/health" -UseBasicParsing -TimeoutSec 5
        if ($health.Content -match '"ok":\s*true') {
            Write-Step "The MT5 bridge is already listening on http://127.0.0.1:8765."
            exit 0
        }
    } catch {
        Write-Step "Replacing the bridge that is not connected to MT5."
    }

    Stop-OurBridge
}

$env:BRIDGE_TOKEN = $bridgeToken
$env:BRIDGE_HOST = "127.0.0.1"
$env:BRIDGE_PORT = "8765"
$env:LARAVEL_API_URL = $appUrl
$env:LARAVEL_API_TOKEN = $laravelToken
$env:MT5_TERMINAL_PATH = $mt5
$env:MT5_POLL_SECONDS = "1"
$env:MT5_QUOTE_SECONDS = "0.02"

if (Test-Path -LiteralPath $LogFile) {
    Remove-Item -LiteralPath $LogFile -Force
}

Write-Step "Starting the bridge against $appUrl."
    $process = Start-Process -FilePath $venvPython -ArgumentList @((Join-Path $BridgeDir "mt5_bridge.py")) -WorkingDirectory $BridgeDir -WindowStyle Hidden -RedirectStandardOutput $LogFile -RedirectStandardError (Join-Path $BridgeDir "bridge-error.log") -PassThru
Set-Content -LiteralPath $PidFile -Value $process.Id -Encoding ascii

$deadline = (Get-Date).AddSeconds(40)
do {
    Start-Sleep -Seconds 1
    if ($process.HasExited) {
        $details = (Read-SharedText $LogFile) + (Read-SharedText (Join-Path $BridgeDir "bridge-error.log"))
        throw "The bridge stopped before it could listen. $details"
    }

    $output = Read-SharedText $LogFile
    if ($output -match "listening on") {
        Write-Step "MT5 bridge is listening on http://127.0.0.1:8765."
        if (-not $Detach) {
            Write-Step "This window can stay open to watch the log. Closing it does not stop the bridge."
            Get-Content -LiteralPath $LogFile -Wait
        }
        exit 0
    }
} while ((Get-Date) -lt $deadline)

throw "The bridge did not report that it is listening. See $LogFile."
