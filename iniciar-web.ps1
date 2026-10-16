param([ValidateRange(1024, 65535)][int]$Puerto = 8003)

& (Join-Path $PSScriptRoot 'iniciar.ps1') -Puerto $Puerto
exit $LASTEXITCODE
