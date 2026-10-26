<?php
declare(strict_types=1);
namespace Juzimder;

/** Single allowlist shared by typed component operations and static HTML export. */
final class ComponentStylePolicy {
    private const PROPERTIES = [
        'display','flex-direction','justify-content','align-items','flex-wrap','align-self','grid-template-columns','gap','flex','scroll-snap-type','scroll-snap-align','scrollbar-width',
        'width','height','min-width','max-width','min-height','max-height','padding','padding-top','padding-right','padding-bottom','padding-left',
        'background-image','background-size','background-position','background-repeat','position','top','right','bottom','left','justify-self','overflow','z-index','margin','margin-top','margin-right','margin-bottom','margin-left','color','background-color','font-family','font-size','font-weight',
        'font-style','text-transform','list-style-type','box-shadow','row-gap','column-gap','overflow-x','overflow-y','background-position-x','background-position-y','background-attachment','background-origin','background-clip','text-decoration-line','text-decoration-thickness','text-decoration-style','text-decoration-color','line-height','letter-spacing','text-align','text-decoration','border','border-top','border-bottom','border-radius','object-fit','aspect-ratio','opacity','cursor','filter',
        '--juzimder-color-ink','--juzimder-color-forest','--juzimder-color-canvas','--juzimder-color-paper','--juzimder-color-body','--juzimder-color-muted','--juzimder-color-line','--juzimder-color-accent',
        '--juzimder-font-family','--juzimder-font-heading','--juzimder-font-body','--juzimder-font-weight-body','--juzimder-font-weight-heading',
        '--juzimder-font-size-base','--juzimder-font-size-h1','--juzimder-font-size-h2','--juzimder-font-size-h3','--juzimder-font-size-h4','--juzimder-font-size-h5','--juzimder-font-size-h6',
        '--juzimder-line-height-body','--juzimder-line-height-heading','--juzimder-letter-spacing','--juzimder-radius',
        'border-top-left-radius','border-top-right-radius','border-bottom-right-radius','border-bottom-left-radius',
        'border-top-width','border-right-width','border-bottom-width','border-left-width',
        'border-top-style','border-right-style','border-bottom-style','border-left-style',
        'border-top-color','border-right-color','border-bottom-color','border-left-color',
        'border-image-source','border-image-slice','border-image-width','border-image-outset','border-image-repeat',
    ];

    /** @return list<string> */
    public static function properties(): array {
        return self::PROPERTIES;
    }

    public static function allows(string $property): bool {
        return in_array($property, self::PROPERTIES, true);
    }
}
