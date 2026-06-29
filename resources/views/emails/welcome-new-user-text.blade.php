Welcome to e-Salary CLAB

Your account has been created successfully.

Dear {{ $user->name }},

An administrator has created an account for you on the e-Salary CLAB system.
Please use the credentials below to log in:

Login ID:           {{ $user->username }}
Email:              {{ $user->email }}
Temporary Password: {{ $plainPassword }}
Role:               {{ ucfirst(str_replace('_', ' ', $user->role)) }}

SECURITY NOTICE: For your protection, please log in and change your password
as soon as possible. Do not share these credentials with anyone.

Log in here: {{ url('/login') }}

If you did not expect this account or have any questions, please contact the
CLAB administration.

--
e-Salary CLAB System
This is an automated notification. Please do not reply to this email.
(c) {{ date('Y') }} e-Salary CLAB. All rights reserved.
