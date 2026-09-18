// Method notifikasi toastr yang benar-benar tersedia; tipe di luar ini (mis. 'danger', 'notify') dulu meng-crash toast() karena window.toastr[type] undefined (TB-14233).
const TOASTR_METHODS = ['success', 'info', 'warning', 'error'];

// Alias tipe umum non-toastr ke method yang setara.
const TOASTR_ALIASES = {
    danger: 'error',
    fail: 'error',
    failed: 'error',
    warn: 'warning',
    notice: 'info',
    note: 'info',
    primary: 'info',
    secondary: 'info',
    default: 'info',
};

// Petakan sembarang tipe ke satu method toastr yang valid; default 'info' supaya notifikasi tidak pernah meng-crash halaman karena tipe tak dikenal.
export function resolveToastrMethod(type) {
    const key = String(type == null ? '' : type).toLowerCase();
    if (TOASTR_METHODS.indexOf(key) !== -1) {
        return key;
    }
    if (Object.prototype.hasOwnProperty.call(TOASTR_ALIASES, key)) {
        return TOASTR_ALIASES[key];
    }

    return 'info';
}
