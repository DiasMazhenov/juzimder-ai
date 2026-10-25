const MAX_HTML_BYTES = 32768;
const MAX_STYLE_BYTES = 16384;
const MAX_SCRIPT_BYTES = 16384;
const MAX_TOTAL_BYTES = 65536;

function byteLength(value) {
  return new TextEncoder().encode(value).length;
}

export function createSandboxNonce() {
  const bytes = new Uint8Array(16);
  globalThis.crypto.getRandomValues(bytes);
  return Array.from(bytes, (byte) => byte.toString(16).padStart(2, "0")).join("");
}

export function pageSandboxNonce(documentRef = globalThis.document) {
  const candidate = documentRef?.querySelector?.(
    'meta[name="juzimder-csp-nonce"]',
  )?.content;
  return typeof candidate === "string" && /^[a-f0-9]{32}$/.test(candidate)
    ? candidate
    : createSandboxNonce();
}

export function validateSandboxPayload(payload) {
  if (!payload || typeof payload !== "object" || Array.isArray(payload))
    throw new TypeError("HTML-элемент ожидает объект HTML/CSS/JavaScript.");
  const keys = Object.keys(payload).sort();
  if (
    keys.length !== 3 ||
    keys[0] !== "css" ||
    keys[1] !== "html" ||
    keys[2] !== "js" ||
    keys.some((key) => typeof payload[key] !== "string")
  )
    throw new TypeError("В HTML-элементе допустимы только поля HTML, CSS и JavaScript.");
  const htmlBytes = byteLength(payload.html),
    cssBytes = byteLength(payload.css),
    jsBytes = byteLength(payload.js);
  if (
    htmlBytes > MAX_HTML_BYTES ||
    cssBytes > MAX_STYLE_BYTES ||
    jsBytes > MAX_SCRIPT_BYTES ||
    htmlBytes + cssBytes + jsBytes > MAX_TOTAL_BYTES
  )
    throw new RangeError("Код HTML-элемента превышает допустимый размер.");
  if (/<\/style\s*>/i.test(payload.css))
    throw new TypeError("В CSS HTML-элемента нельзя закрывать тег style.");
  return { html: payload.html, css: payload.css, js: payload.js };
}

function base64UTF8(value) {
  const bytes = new TextEncoder().encode(value);
  let binary = "";
  for (let offset = 0; offset < bytes.length; offset += 8192)
    binary += String.fromCharCode(...bytes.subarray(offset, offset + 8192));
  return btoa(binary);
}

export function createSandboxDocument(value, nonce = createSandboxNonce()) {
  const payload = validateSandboxPayload(value);
  if (!/^[a-f0-9]{32}$/.test(nonce))
    throw new TypeError("Некорректный nonce изолированной песочницы.");
  const script =
    payload.js === ""
      ? ""
      : `<script nonce="${nonce}">(function(){const data=${JSON.stringify(base64UTF8(payload.js))};const bytes=Uint8Array.from(atob(data),c=>c.charCodeAt(0));const code=new TextDecoder().decode(bytes);const node=document.createElement("script");node.nonce=document.currentScript.nonce;node.textContent=code;document.body.append(node);})();</script>`;
  const csp = `default-src 'none'; script-src 'nonce-${nonce}'; connect-src 'none'; img-src data: blob:; media-src data: blob:; font-src data:; style-src 'unsafe-inline'; frame-src 'none'; object-src 'none'; base-uri 'none'; form-action 'none'`;
  return `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta http-equiv="Content-Security-Policy" content="${csp}"><style>*,*::before,*::after{box-sizing:border-box}body{margin:0}${payload.css}</style></head><body>${payload.html}${script}</body></html>`;
}

export function sandboxFrame(
  payload,
  {
    id = "",
    runScripts = false,
    title = "HTML-элемент",
    nonce = pageSandboxNonce(),
  } = {},
) {
  const srcdoc = createSandboxDocument(payload, nonce),
    sandbox = runScripts && payload.js !== "" ? "allow-scripts" : "";
  const attr = (value) =>
    String(value).replace(/[&<>"']/g, (character) =>
      ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[
        character
      ],
    );
  return `<iframe class="jz-zero-element jz-zero-code" data-ze="${attr(id)}" data-zero-code="true"${runScripts ? ' data-code-running="true"' : ""} title="${attr(title)}" sandbox="${sandbox}" referrerpolicy="no-referrer" srcdoc="${attr(srcdoc)}"></iframe>`;
}
