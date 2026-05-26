# Project Routes

## Public Site Pages
- `GET /` — Welcome (homepage)
- `GET /dashboard` — Dashboard (auth+verified)
- `GET /profile` — Profile edit
- `PATCH /profile` — Profile update
- `DELETE /profile` — Profile delete
- `GET /login` — User login
- `POST /login` — User login submit
- `POST /logout` — User logout
- `GET /register` — User registration
- `POST /register` — User registration submit
- `GET /forgot-password` — Password reset request
- `POST /forgot-password` — Password reset email submit
- `GET /reset-password/{token}` — Reset form
- `POST /reset-password` — Reset submit
- `GET /confirm-password` — Confirm password
- `POST /confirm-password` — Confirm password submit
- `GET /verify-email` — Email verification notice
- `POST /email/verification-notification` — Resend verification
- `GET /verify-email/{id}/{hash}` — Verify email

## Admin Pages (UI)
- `GET /admin` — Admin home (redirects based on role)
- `GET /admin/login` — Admin login
- `POST /admin/login` — Admin login submit
- `POST /admin/logout` — Admin logout
- `GET /admin/dashboard` — Admin dashboard
- `GET /admin/reports` — Admin reports UI
- `GET /admin/users` — Admin users UI

## Public/API Endpoints
- `POST /api/bot/submit` — Bot submission (public)

## Authenticated API (requires auth)
- `GET /api/categories`
- `GET /api/items`
- `POST /api/items`
- `GET /api/items/{item}`
- `PATCH /api/items/{item}`
- `DELETE /api/items/{item}`

## Dashboard Data (auth)
- `GET /dashboard/data/categories`
- `GET /dashboard/data/items`
- `POST /dashboard/data/items`

## Admin API (auth + role)
- `GET /admin/api/users`
- `POST /admin/api/users`
- `GET /admin/api/users/{user}`
- `PATCH /admin/api/users/{user}`
- `DELETE /admin/api/users/{user}`
- `GET /admin/api/reports`
- `POST /admin/api/reports`
- `GET /admin/api/reports/{report}`
- `PATCH /admin/api/reports/{report}`
- `DELETE /admin/api/reports/{report}`

## System / Framework
- `GET /sanctum/csrf-cookie`
- `GET /storage/{path}`
- `PUT /storage/{path}`
- `GET /up`
