// Makes the web-size and thumbnail copies of a photo off the main thread,
// so the page stays smooth to scroll while uploads run.
self.onmessage = async ev => {
  const {id, file} = ev.data;
  try {
    const bmp = await createImageBitmap(file, {imageOrientation: 'from-image'});
    const fit = (src, max) => {
      const s = Math.min(1, max / Math.max(src.width, src.height));
      const c = new OffscreenCanvas(Math.round(src.width * s), Math.round(src.height * s));
      c.getContext('2d').drawImage(src, 0, 0, c.width, c.height);
      return c;
    };
    const width = bmp.width, height = bmp.height, webCanvas = fit(bmp, 2048); bmp.close();
    const web = await webCanvas.convertToBlob({type: 'image/jpeg', quality: 0.85});
    const thumb = await fit(webCanvas, 600).convertToBlob({type: 'image/jpeg', quality: 0.85});
    self.postMessage({id, web, thumb, width, height});
  } catch (err) { self.postMessage({id, error: String(err && err.message || err)}); }
};
