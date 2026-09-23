export default function EmptyState({
    title,
    description,
    action,
}: {
    title: string;
    description?: string;
    action?: React.ReactNode;
}) {
    return (
        <div className="flex flex-col items-center justify-center rounded-xl border border-dashed p-12 text-center">
            <h3 className="text-base font-medium">{title}</h3>
            {description && (
                <p className="text-muted-foreground mt-1 max-w-md text-sm">
                    {description}
                </p>
            )}
            {action && <div className="mt-4">{action}</div>}
        </div>
    );
}
