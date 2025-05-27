#!/bin/bash

# Create symbolic links for all hooks
for hook in pre-commit pre-push commit-msg; do
    # Remove existing hook if it exists
    [ -f "../.git/hooks/$hook" ] && rm "../.git/hooks/$hook"
    
    # Create symbolic link
    ln -s "../../.ci/hooks/$hook" "../.git/hooks/$hook"
    
    # Make the hook executable
    chmod +x "../../.ci/hooks/$hook"
done

echo "Git hooks installed successfully!"
