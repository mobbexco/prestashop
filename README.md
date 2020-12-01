# Mobbex

Módulo del Gateway Mobbex para Prestashop.

### Versión Actual

- 2.0.3

### Versiones de Prestashop Soportadas  

- 1.6
- 1.7

### Soluciones a Posibles Problemas

#### Hooks no conectados en Multi-Sitio
- Identificar el ID de Hook, en la tabla ps_hook.
- Identificar ID del módulo en la tabla ps_module.
- En ps_hook_module, insertar ambos IDs respetando el formato de la tabla.
