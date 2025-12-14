@echo off
echo Redeploying to Vercel...
echo.

REM Add all files
git add .

REM Commit
echo Committing changes...
git commit -m "Fix: Update Vercel API functions with proper syntax"

REM Push
echo Pushing to GitHub...
git push

echo.
echo Done! Pushed to GitHub!
echo.
echo Vercel is auto-deploying...
echo Check: https://vercel.com/dashboard
echo.
echo Your app: https://mydrive-in.vercel.app
echo.
echo Wait 30-60 seconds, then test!
pause
