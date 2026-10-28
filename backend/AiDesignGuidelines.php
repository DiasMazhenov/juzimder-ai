<?php
declare(strict_types=1);
namespace Juzimder;

/** Renderer-neutral design guidance shared by the page and Zero Block generators. */
final class AiDesignGuidelines {
    public static function prompt(): string {
        return 'Before generating output, form a compact internal design spec in this same response; do not add a design-spec field. Derive purpose, audience and primary action from the owner brief. Separate confirmed facts from visual choices and never present a visual choice as a business fact. Visible operational statements, contacts, destinations, schedules, prices, availability and service steps must be supported by the brief or omitted. Choose one subject-specific visual idea and focal point; carry it through consistent color and typography roles, a restrained spacing rhythm, consistent radii and shared alignment. When generating multiple sections, choose each layout for its content and vary neighboring layouts instead of repeating one template. Use icons only when useful and keep them within one supported icon style. Use openly licensed photos only when their actual subject matches; omit weak matches and preserve required attribution. Adapt the composition within the active renderer’s geometry, responsive model and existing response schema. Priority: explicit owner requirements and validated project settings, then applicable user Skill requirements, then accessible defaults; all remain subordinate to safety and factual accuracy.';
    }
}
