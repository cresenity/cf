/* eslint-disable no-underscore-dangle */
const findModifierArgument = (modifiers, target, offset = 1) => {
    return modifiers[modifiers.indexOf(target) + offset];
};

const buildConfigFromModifiers = (modifiers, expression, evaluate) => {
    const config = evaluate(expression);

    return config;
};

// data('autoNumeric') hilang setelah 'destroy'; set/get sesudah itu melempar $.error
const isInitialized = (el) => typeof $(el).data('autoNumeric') === 'object';

const valueChangeCallback = (el) => {
    return () => {
        if (!el._x_model || !isInitialized(el)) {
            return;
        }
        let value = $(el).autoNumeric('get');

        el._x_model.set(value);
    };
};

const setValue = (el, value) => {
    // effect yang sudah antre masih dijalankan sekali setelah elemen dibersihkan (Vue reactivity 3.1)
    if (value === undefined || value === null || !isInitialized(el)) {
        return;
    }
    // nilai non-numerik (mis. NaN dari perhitungan) membuat autoNumeric melempar dan menghentikan antrean effect lain
    if (!$.isNumeric(+value)) {
        console.warn('x-autonumeric: nilai bukan angka diabaikan', value, el);
        return;
    }
    $(el).autoNumeric('set', value);
};

export default function (Alpine) {
    Alpine.magic('autonumeric', (el) => {
        if (el.__autonumeric) {
            return el.__autonumeric;
        }
    });

    Alpine.directive('autonumeric', (el, { modifiers, expression }, { effect, evaluate, cleanup }) => {
        if(typeof jQuery ==='undefined' || typeof $ ==='undefined') {
            console.error('Error autonumeric need jquery');
            return;
        }

        if(typeof $.prototype.autoNumeric ==='undefined') {
            console.error('Error AutoNumeric is not defined');
            return;
        }
        if (el._x_model) {
            // Find the model directive (due to modifiers, we don't know the name upfront)
            // and remove the default behaviours
            const directive = Alpine.prefixed('model');
            Object.keys(el._x_attributeCleanups).forEach(key => {
                if (key.startsWith(directive)) {
                    el._x_attributeCleanups[directive][0]();
                    delete el._x_attributeCleanups[directive];
                }
            });
            el._x_forceModelUpdate = () => {};
        }
        const config = modifiers.length === 0
            ? expression ? evaluate(expression) : {}
            : buildConfigFromModifiers(modifiers, expression, evaluate);

        if (!el.__autonumeric) {
            $(el).autoNumeric('init', config);
            el.__autonumeric = $(el).data('autoNumeric');
            const changeHandler = valueChangeCallback(el);
            $(el).bind('blur focusout change', changeHandler);

            // satu effect saja: cleanup elementBoundEffect hanya melepas effect terakhir yang didaftarkan
            effect(() => {
                Alpine.mutateDom(() => {
                    if (el._x_model) {
                        setValue(el, el._x_model.get());
                    }
                    if (el._x_bindings && el._x_bindings.value) {
                        setValue(el, el._x_bindings.value);
                    }
                });
            });
            cleanup(()=>{
                $(el).unbind('blur focusout change', changeHandler);
                if (isInitialized(el)) {
                    $(el).autoNumeric('destroy');
                }
            });
        }
    });
}
