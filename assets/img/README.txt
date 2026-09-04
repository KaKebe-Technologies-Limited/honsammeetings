The Minister's photo is currently:

    min1.jpg

Its filename is set once in ../../config.php (the MINISTER_PHOTO constant),
so it is used consistently in the header, login page, and on printed
sheets (where it's shown circular, top-right).

The source photo is a wide press-conference shot rather than a tight
headshot, so the circular crop is zoomed in on the face via the shared
`.photo-fit` CSS rule (in both style.css and print.css) rather than a
plain object-fit:cover, which would otherwise show the whole scene tiny.
If you swap in a different photo with the subject framed differently,
you may need to adjust the `transform` / `transform-origin` values on
`.photo-fit` to re-center the crop.

If the file is missing, the header/login page still works: it falls back
to a plain "SE" badge, and the print header simply omits the photo.
