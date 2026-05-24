# OrganizerPro

## Deployment

When asked to deploy, run:
```bash
ssh -i ~/Downloads/LightsailDefaultKey-ap-south-1.pem ubuntu@13.232.249.159 "cd /var/www/laravel-app && git pull origin main && sudo chown -R www-data:www-data storage bootstrap/cache && sudo chmod -R 775 storage bootstrap/cache && php artisan optimize:clear"
```

- **Host:** 13.232.249.159
- **User:** ubuntu
- **Key:** ~/Downloads/LightsailDefaultKey-ap-south-1.pem
- **Project Path on Server:** /var/www/laravel-app
