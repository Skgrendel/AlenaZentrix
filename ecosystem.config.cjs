module.exports = {
    apps: [
        {
            name: 'alenazentrix-queue',
            script: 'php',
            args: 'artisan queue:work --sleep=3 --tries=3 --max-time=3600 --memory=256',
            cwd: __dirname,
            interpreter: 'none',
            autorestart: true,
            watch: false,
            max_memory_restart: '300M',
            env: {
                APP_ENV: 'production',
            },
        },
        {
            name: 'alenazentrix-scheduler',
            script: 'php',
            args: 'artisan schedule:work',
            cwd: __dirname,
            interpreter: 'none',
            autorestart: true,
            watch: false,
            env: {
                APP_ENV: 'production',
            },
        },
    ],
};
