<style>
    .fi-app-logo {
        display: flex;
        align-items: center;
        gap: 0.6rem;
    }

    .fi-app-logo-image {
        height: 2.75rem;
        width: auto;
    }

    .fi-app-logo-image-login {
        display: none;
    }

    .fi-app-logo-text {
        font-size: 1rem;
        font-weight: 600;
        line-height: 1.2;
    }

    .fi-simple-header .fi-app-logo {
        flex-direction: column;
        gap: 0;
        text-align: center;
        margin-top: -3rem;
        margin-bottom: 0;
    }

    .fi-simple-header .fi-logo {
        height: auto !important;
    }

    .fi-simple-header .fi-app-logo-image {
        height: 4.75rem;
    }

    .fi-simple-header .fi-app-logo-image-default {
        display: none;
    }

    .fi-simple-header .fi-app-logo-image-login {
        display: block;
        height: 12.5rem;
    }

    .fi-simple-header .fi-app-logo-text {
        display: none;
    }

    .fi-simple-header .fi-simple-header-heading {
        margin-top: -3rem;
        font-size: 1rem;
        line-height: 1.25;
    }

    @media (max-width: 640px) {
        .fi-simple-header .fi-app-logo {
            margin-top: -1.5rem;
        }

        .fi-simple-header .fi-app-logo-image-login {
            height: 7.5rem;
            max-width: min(80vw, 360px);
        }

        .fi-simple-header .fi-simple-header-heading {
            margin-top: -1.2rem;
            font-size: 0.95rem;
        }
    }

    @media (min-width: 641px) and (max-width: 1024px) {
        .fi-simple-header .fi-app-logo {
            margin-top: -2.25rem;
        }

        .fi-simple-header .fi-app-logo-image-login {
            height: 9.5rem;
            max-width: min(70vw, 460px);
        }

        .fi-simple-header .fi-simple-header-heading {
            margin-top: -2rem;
        }
    }
</style>
