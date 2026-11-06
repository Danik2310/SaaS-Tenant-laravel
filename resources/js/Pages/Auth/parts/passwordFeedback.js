/**
 * Both empty is not a match: an untouched confirmation has verified nothing,
 * and `password === confirmation` would otherwise report success on two blanks.
 * The tenant signup request only enforces `confirmed` plus Password::defaults()
 * (min 8), so equality is the whole rule this helper needs to carry.
 */
export const passwordsMatch = (password, confirmation) =>
    password !== '' && confirmation !== '' && password === confirmation;
