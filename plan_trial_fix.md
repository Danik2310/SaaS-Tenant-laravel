PLAN DE CORRECCIÓN (ready-to-implement, after plan approval):

## Problema
Usuario reporta: 'no llega la información del dominio registrado ni el tiempo de conclusión del plan trial' en la página de éxito.

## Causa confirmada
Datos muestran TEN-000001 creado como plan 'Free' (plan_id=2, status='Active', trial_ends_at=NULL). En successResponse se pasa trialDays solo si status==='Trial'. Para 'trial' slug, conPath conPlan() fuerza status='Trial' y fija trial_ends_at. Para 'free', queda 'Active' → trialDays=null. Eso es **correcto según los tests actuales**.

## Posibles mejoras (sin romper tests)

### A) Mostrar trial también cuando trial_ends_at existe (independiente de status)
Cambiar successResponse en ambos controladores:
- TenantRegistrationController::successResponse() line ~137-141
- RegistrationCheckoutController::successResponse() line ~129-133

Desde:
`php
'trialDays' => ->status === 'Trial'
    ? (int) config('tenancy.trial_days', 14)
    : null,
`
A:
`php
 = null;
if (->status === 'Trial') {
     = (int) config('tenancy.trial_days', 14);
} elseif (->trial_ends_at) {
     = (int) ->trial_ends_at->diffInDays(now(), false);
    if ( < 0) {  = abs(); } // días restantes
    // o mejor días totales vs restantes? tests esperan 14 para status Trial
}
`
Pero **tests** esperan 	rialDays === config('tenancy.trial_days',14) cuando status==='Trial' y 
ull para Free (status Active). Si cambiamos, rompemos tests. **NO HACERLO** sin decidir.

### B) Asegurar que 'trial' seleccionado llegue como 'trial'
Frontend: en TenantRegister, cuando se envía, el plan viene de data.plan. Para el plan 'Trial' (slug trial), debe enviarse 'trial'. El botón de submit ya envía el form; el valor correcto depende de la selección.

### C) Mostrar dominio aunque sea tenant existente (ya existe) — pero aquí hay dominio. Revisar UI success: lee domain de props. OK.

### D) Mejor UX: mostrar trial_ends_at o mensaje 'Trial ends on...'
Cambiar TenantRegisterSuccess.jsx para mostrar fecha si hay trial_ends_at pero status no Trial? También puede romper estética.

## Recomendación inmediata
1. Verificar qué plan envió el usuario (si eligió 'Free' en lugar de 'Trial'). Eso explica todo.
2. Si quiere que Free con trial_ends_at muestre 'X días restantes', hay que cambiar lógica. **PREGUNTAR** antes de editar.
3. NO cambiar backend para 'forzar' mostrar trial si no corresponde al status actual (respeta lógica de negocio).

**Ready to execute after approval.**
