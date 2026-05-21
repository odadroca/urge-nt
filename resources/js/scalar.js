// INFRA-01 / PB-5: self-host @scalar/api-reference at the version pinned
// in package.json. Previously /docs loaded the package via the public
// jsdelivr CDN with no SRI/version pin, so any CDN or package compromise
// would execute arbitrary JS in every URGE visitor's browser, same-origin
// as OAuth + SPA.
import { createApiReference } from '@scalar/api-reference';

const el = document.getElementById('api-reference');
if (el) {
    createApiReference(el, {
        url: el.dataset.url || '/openapi.json',
        documentDownloadType: 'none',
    });
}
