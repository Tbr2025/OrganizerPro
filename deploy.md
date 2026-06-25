# Deployment Instructions

## Server Details
- **Host:** 13.232.249.159
- **User:** ubuntu
- **Key:** ~/Downloads/LightsailDefaultKey-ap-south-1.pem
- **Project Path:** /var/www/laravel-app

## Git / GitHub
- **GitHub account for this repo:** `navasfazil1004`
- **Remote:** `https://navasfazil1004@github.com/Tbr2025/OrganizerPro.git`
- Push commits with: `git push origin main` (authenticates as `navasfazil1004`)

## Deploy Command
```bash
ssh -i ~/Downloads/LightsailDefaultKey-ap-south-1.pem ubuntu@13.232.249.159 "cd /var/www/laravel-app && git pull origin main && sudo chown -R www-data:www-data storage bootstrap/cache && sudo chmod -R 775 storage bootstrap/cache && php artisan optimize:clear"
```
