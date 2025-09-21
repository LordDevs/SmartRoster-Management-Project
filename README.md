Requirements
PHP 8+ with SQLite support (pdo_sqlite).

Node.js 18+ for build scripts and presentation generation.

Optional: Python 3.10+ for image utilities.

Environment Setup
Clone repository

git clone <repo-url>
cd escala_hillbillys
Install Node dependencies (optional)

npm install
Configure database

The db.sqlite file already includes a basic schema.

To start from scratch, remove the file and rebuild the database by running the population scripts (populate_employees.php, etc.) as needed.

Start local PHP server

php -S localhost:8000
The application will be available at http://localhost:8000.

Build assets (when needed)

TailwindCSS/PostCSS: adjust the command to match your preferred workflow:

npx tailwindcss -i ./input.css -o ./output.css --watch
Presentation scripts (slides_template.js, answer.js) can be executed with node.

Core Features
Authentication and Profiles
Log in through index.php with PHP sessions.

User roles: admin, manager, and employee.

The helper functions requireLogin, requireAdmin, and requirePrivileged enforce proper access.

Dashboard
Employee, shift, and worked-hours statistics (dashboard.php).

Daily charts powered by Chart.js.

Employee and User Management
Create, edit, delete, and link employees to stores.

Manage system users (usuarios_listar.php, usuario_criar.php, etc.).

Schedules/Shifts
Create schedules manually (escala_criar.php) or via automatic suggestions with hour balancing.

List and edit existing schedules (escala_listar.php).

Time Clock
Register check-ins and check-outs (ponto_registrar.php).

Correct and review time entries (ponto_corrigir.php, ponto_listar.php).

Notifications
notifications_api.php exposes endpoints to count, list, and mark notifications.

Widgets (notifications_widget.php) and scripts (init_notifications.php) enable page integration.

Reports and Exports
Export employees, shifts, and records to CSV.

Performance reports (desempenho.php, relatorios.php).

Utilities
Node scripts for building PPTX presentations (via pptxgenjs).

Python for creating montages and converting slides into images.

Helper Scripts
Script  Primary Use
create_montage.py       Generates image montages (Pillow).
pptx_to_img.py  Converts .pptx slides into images.
slides_template.js      Template for generating full PPTX presentations.
answer.js       Adaptable variant of slides_template.js.
