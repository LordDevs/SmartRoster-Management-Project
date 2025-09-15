<?php
/**
 * Navbar template for Escala Hillbillys.
 *
 * This file centralizes the markup for the top navigation bar used on most
 * administrator and manager pages. To use it, each page should set
 * `$activePage` to one of the following values before including this file:
 *
 *   'usuarios'    => Funcionários
 *   'escalas'     => Escalas
 *   'calendario'  => Calendário
 *   'pontos'      => Pontos
 *   'relatorios'  => Relatórios
 *   'desempenho'  => Desempenho
 *   'metricas'    => Métricas (Analytics)
 *   'lojas'       => Lojas (admin)
 *   'loja'        => Loja (manager)
 *
 * The file determines the current user's role (admin, manager, employee)
 * using the helper currentUser() from config.php if available. It falls
 * back to $_SESSION['user_role'] when currentUser() is not defined.
 */

// Determine the current user role. Use currentUser() if defined.
if (function_exists('currentUser')) {
    $u = currentUser();
    $role = $u['role'] ?? null;
} else {
    // Fallback: use session variable if present
    $role = $_SESSION['user_role'] ?? null;
}

// Determine brand link based on role
if ($role === 'manager') {
    $brandHref = 'manager_dashboard.php';
    $brandText = 'Escala Hillbillys – Gerente';
} elseif ($role === 'employee') {
    // Employees should not normally include this nav; but default to portal
    $brandHref = 'portal.php';
    $brandText = 'Escala Hillbillys';
} else {
    // Default to admin
    $brandHref = 'dashboard.php';
    $brandText = 'Escala Hillbillys';
}

// Helper to mark a nav item as active. Declare only once.
if (!function_exists('nav_active')) {
    function nav_active(string $page, ?string $activePage): string {
        return ($activePage === $page) ? 'active' : '';
    }
}

?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?php echo htmlspecialchars($brandHref); ?>">
            <?php echo htmlspecialchars($brandText); ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Alternar navegação">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?php echo nav_active('usuarios', $activePage); ?>" href="usuarios_listar.php">Funcionários</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo nav_active('escalas', $activePage); ?>" href="escala_listar.php">Escalas</a>
                </li>
                <li class="nav-item">
                    <!-- O item "Calendário" agora aponta para a visualização semanal recém-criada -->
                    <a class="nav-link <?php echo nav_active('calendario', $activePage); ?>" href="escala_calendario_semana.php">Calendário</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo nav_active('pontos', $activePage); ?>" href="ponto_listar.php">Pontos</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo nav_active('relatorios', $activePage); ?>" href="relatorios.php">Relatórios</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo nav_active('desempenho', $activePage); ?>" href="desempenho.php">Desempenho</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo nav_active('metricas', $activePage); ?>" href="analytics.php">Métricas</a>
                </li>
                <?php if ($role === 'admin'): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo nav_active('lojas', $activePage); ?>" href="lojas_listar.php">Lojas</a>
                </li>
                <?php elseif ($role === 'manager'): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo nav_active('loja', $activePage); ?>" href="loja_gerenciar_updated.php">Loja</a>
                </li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="logout.php">Sair</a></li>
            </ul>
        </div>
    </div>
</nav>