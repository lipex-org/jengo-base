/// <reference types="vite/client" />

interface ImportMeta {
    readonly env: ImportMetaEnv
    readonly glob: (pattern: string | string[], options?: { eager?: boolean, as?: string, query?: string | Record<string, string | number | boolean>, import?: string }) => Record<string, any>
}

interface ImportMetaEnv {
    readonly VITE_APP_TITLE: string
    // more env variables...
}