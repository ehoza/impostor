/**
 * Avatar filenames for player selection.
 * Must match portrait-no-border{N}.png in public/images/avatars/
 */
export const AVATAR_COUNT = 200;

export const AVATARS: string[] = Array.from({ length: AVATAR_COUNT }, (_, i) => `portrait-no-border${i + 1}.png`);

export function avatarUrl(filename: string): string {
    return `/images/avatars/${filename}`;
}
