Requisitos
PHP 8+ com suporte a SQLite (pdo_sqlite).

Node.js 18+ para scripts de build e geração de apresentações.

Opcional: Python 3.10+ para os utilitários de imagens.

Configuração do Ambiente
Clonar repositório

git clone <url-do-repo>
cd escala_hillbillys
Instalar dependências Node (opcional)

npm install
Configurar banco de dados

O arquivo db.sqlite já contém um esquema básico.

Para começar do zero, remova o arquivo e recrie a base executando os scripts de população (populate_employees.php, etc.) conforme necessário.

Iniciar servidor PHP local

php -S localhost:8000
A aplicação ficará acessível em http://localhost:8000.

Build de assets (quando necessário)

TailwindCSS/PostCSS: ajustar o comando conforme o fluxo desejado:

npx tailwindcss -i ./input.css -o ./output.css --watch
Scripts de apresentação (slides_template.js, answer.js) podem ser executados com node.

Funcionalidades Principais
Autenticação e Perfis
Login via index.php com sessão PHP.

Perfis de usuário: admin, manager e employee.

Helpers requireLogin, requireAdmin e requirePrivileged garantem o acesso correto.

Painel de Controle
Estatísticas de funcionários, turnos e horas trabalhadas (dashboard.php).

Gráficos por dia usando Chart.js.

Gerenciamento de Funcionários e Usuários
Cadastro, edição, exclusão e vinculação de funcionários a lojas.

Controle de usuários do sistema (usuarios_listar.php, usuario_criar.php etc.).

Escalas/Turnos
Criação manual (escala_criar.php) ou por sugestão automática com balanceamento de horas.

Listagem e edição de escalas existentes (escala_listar.php).

Registro de Ponto
Registro de entrada/saída (ponto_registrar.php).

Correção e listagem de apontamentos de tempo (ponto_corrigir.php, ponto_listar.php).

Notificações
notifications_api.php oferece endpoints para contagem, listagem e marcação de notificações.

Widgets (notifications_widget.php) e scripts (init_notifications.php) permitem integração em páginas.

Relatórios e Exportações
Exportação de funcionários, turnos e registros em CSV.

Relatórios de desempenho (desempenho.php, relatorios.php).

Utilitários
Scripts Node para criação de apresentações PPTX (via pptxgenjs).

Python para geração de montagens e conversão de slides em imagens.

Scripts Auxiliares
Script	Uso Principal
create_montage.py	Geração de montagens de imagens (pillow).
pptx_to_img.py	Converte slides .pptx em imagens.
slides_template.js	Modelo para gerar apresentações completas em PPTX.
answer.js	Variante adaptável de slides_template.js.
