test('a small heading', async ({ page }) => {
    await expect(page.locator('h1')).not.toHaveClass(/font-semibold/);
});
