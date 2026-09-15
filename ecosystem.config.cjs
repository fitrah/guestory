const isProduction = process.env.GUESTORY_PM2_MODE === 'production'

const web = {
  name: 'guestory-web',
  cwd: './apps/web',
  script: 'npm',
  args: isProduction
    ? 'run preview -- --host 127.0.0.1 --port 5177'
    : 'run dev -- --host 0.0.0.0 --port 5177',
  autorestart: true,
  env: {
    NODE_ENV: isProduction ? 'production' : 'development',
    VITE_API_BASE_URL: isProduction ? '/api' : 'http://localhost:8088/api',
  },
}

const apiDev = {
  name: 'guestory-api-dev',
  cwd: './apps/api',
  script: 'php',
  args: 'artisan serve --host=0.0.0.0 --port=8088',
  autorestart: true,
  env: {
    APP_ENV: 'local',
  },
}

const queue = {
  name: 'guestory-queue',
  cwd: './apps/api',
  script: 'php',
  args: 'artisan queue:work --sleep=3 --tries=3 --max-time=3600',
  autorestart: true,
  env: {
    APP_ENV: 'production',
  },
}

module.exports = {
  apps: isProduction ? [web, queue] : [web, apiDev],
}
