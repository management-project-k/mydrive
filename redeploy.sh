#!/bin/bash

echo "🔄 Redeploying to Vercel..."
echo ""

# Add all files
git add .

# Commit
echo "📝 Committing changes..."
git commit -m "Fix: Update Vercel API functions with proper syntax"

# Push
echo "⬆️  Pushing to GitHub..."
git push

echo ""
echo "✅ Pushed to GitHub!"
echo ""
echo "⏳ Vercel is auto-deploying..."
echo "   Check: https://vercel.com/dashboard"
echo ""
echo "   Your app: https://mydrive-in.vercel.app"
echo ""
echo "⌛ Wait 30-60 seconds, then test!"
