@echo off
setlocal

:: Create symbolic links for all hooks
for %%h in (pre-commit pre-push commit-msg) do (
    :: Remove existing hook if it exists
    if exist "..\.git\hooks\%%h" del "..\.git\hooks\%%h"

    :: Create symbolic link (requires admin privileges)
    mklink "..\.git\hooks\%%h" "..\..\..\.ci\hooks\%%h" > nul 2>&1

    if errorlevel 1 (
        echo Failed to create symbolic link for %%h. Please run as administrator.
    )
)

echo Git hooks installed successfully!
pause
