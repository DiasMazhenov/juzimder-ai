const YOUTUBE_ID = /^[A-Za-z0-9_-]{11}$/;

/** Convert a user-supplied YouTube/Vimeo URL into a fixed trusted embed URL. */
export function videoEmbedUrl(value) {
  if (typeof value !== "string" || !value || value.length > 2048) return null;
  let url;
  try {
    url = new URL(value);
  } catch {
    return null;
  }
  const authority = value.match(/^https:\/\/([^/?#]*)/i)?.[1] || "";
  if (url.protocol !== "https:" || url.username || url.password || /:\d+$/.test(authority) || url.hash)
    return null;
  const host = url.hostname.toLowerCase();
  if (["youtube.com", "www.youtube.com", "youtube-nocookie.com", "www.youtube-nocookie.com"].includes(host)) {
    const match = url.pathname.match(/^\/(?:embed|shorts|live)\/([A-Za-z0-9_-]{11})\/?$/);
    if (match) return `https://www.youtube-nocookie.com/embed/${match[1]}`;
    if (url.pathname === "/watch") {
      const ids = url.searchParams.getAll("v");
      if (ids.length === 1 && YOUTUBE_ID.test(ids[0]))
        return `https://www.youtube-nocookie.com/embed/${ids[0]}`;
    }
    return null;
  }
  if (host === "youtu.be") {
    const match = url.pathname.match(/^\/([A-Za-z0-9_-]{11})\/?$/);
    return match ? `https://www.youtube-nocookie.com/embed/${match[1]}` : null;
  }
  if (["vimeo.com", "www.vimeo.com"].includes(host)) {
    const match = url.pathname.match(/^\/([0-9]{1,12})\/?$/);
    return match ? `https://player.vimeo.com/video/${match[1]}` : null;
  }
  if (host === "player.vimeo.com") {
    const match = url.pathname.match(/^\/video\/([0-9]{1,12})\/?$/);
    return match ? `https://player.vimeo.com/video/${match[1]}` : null;
  }
  return null;
}
