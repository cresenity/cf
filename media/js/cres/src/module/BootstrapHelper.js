class BootstrapHelper {
    constructor() {
        this.isBootstrap = null;
        this.bootstrapVersion = null;

    }

    check() {
        this.isBootstrap = window.bootstrap !== undefined;
        this.bootstrapVersion = null;
        if(this.isBootstrap && window.bootstrap.Tooltip) {
            this.bootstrapVersion = window.bootstrap.Tooltip.VERSION;
        }
    }
    isBootstrap() {
        return this.isBootstrap;
    }
    isBootstrap5() {
        return this.isBootstrap && !!this.bootstrapVersion && this.bootstrapVersion.startsWith('5');
    }
    isBootstrap4() {
        return this.isBootstrap && !!this.bootstrapVersion && this.bootstrapVersion.startsWith('4');
    }
    bootstrapVersion() {
        return this.bootstrapVersion;
    }
}


const bootstrapHelper = new BootstrapHelper();

export default bootstrapHelper;
