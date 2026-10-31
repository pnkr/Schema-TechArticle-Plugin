# Builds dist/plg_schemaorg_techarticle-<version>.zip and prints its SHA-512 for the update server.
# Usage: powershell -ExecutionPolicy Bypass -File build.ps1

$ErrorActionPreference = 'Stop'

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$root     = $PSScriptRoot
$manifest = [xml](Get-Content -Raw -Encoding UTF8 (Join-Path $root 'techarticle.xml'))
$version  = $manifest.extension.version
$dist     = Join-Path $root 'dist'
$zipPath  = Join-Path $dist "plg_schemaorg_techarticle-$version.zip"

# Everything the installer needs; nothing else is shipped
$include = @('techarticle.xml', 'script.php', 'LICENSE', 'services', 'src', 'forms', 'language')

New-Item -ItemType Directory -Force $dist | Out-Null

if (Test-Path $zipPath) {
    Remove-Item -Force $zipPath -Confirm:$false
}

$zip = [System.IO.Compression.ZipFile]::Open($zipPath, [System.IO.Compression.ZipArchiveMode]::Create)

try {
    foreach ($item in $include) {
        $full = Join-Path $root $item

        if (-not (Test-Path $full)) {
            throw "Missing $item"
        }

        $files = if ((Get-Item $full).PSIsContainer) { Get-ChildItem -Recurse -File $full } else { Get-Item $full }

        foreach ($file in $files) {
            # Forward slashes so the archive extracts correctly on Linux servers
            $entry = $file.FullName.Substring($root.Length + 1).Replace('\', '/')

            [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
                $zip, $file.FullName, $entry, [System.IO.Compression.CompressionLevel]::Optimal
            ) | Out-Null
        }
    }
}
finally {
    $zip.Dispose()
}

$hash = (Get-FileHash -Algorithm SHA512 $zipPath).Hash.ToLower()

Write-Output "Built  $zipPath"
Write-Output "sha512 $hash"
