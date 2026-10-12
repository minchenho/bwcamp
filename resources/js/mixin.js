const snake = (str) =>
    str &&
    str
        .match(/[A-Z]{2,}(?=[A-Z][a-z]+[0-9]*|\b)|[A-Z]?[a-z]+[0-9]*|[A-Z]|[0-9]+/g)
        .map((x) => x.toLowerCase())
        .join("_");

export const mixin = {
    globalFunctions: {
        /**
         * 從頁面上的 window.applicant_data 取得欄位值，並寫入元件
         *
         * @param {object} component 元件本身（傳 this）
         * @param {string} field     欄位名稱，駝峰或蛇形皆可，例如 "isEducating"、"school_or_course"
         * @param {string} camp      保留參數，目前不使用
         */
        getFieldData: async function (component, field, camp = null) {
            const key = snake(field);
            component[key] = window.applicant_data?.[key] ?? null;
            return component[key];
        },
        snake,
    },
};