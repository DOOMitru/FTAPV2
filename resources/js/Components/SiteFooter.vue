<script setup lang="ts">
const year = new Date().getFullYear();
</script>

<template>
    <!--
        Shared by the public shell and the dashboard shell, and deliberately
        part of neither: a footer used by two shells is not furniture belonging
        to one of them.
    -->
    <footer class="site-footer">
        <div class="l-container site-footer__inner">
            <!--
                Decorative: the signature beside it already says the name, so
                alt text would make a screen reader announce it twice.
            -->
            <img class="site-footer__mark" src="/images/header_logo.png" alt="">

            <!--
                Three spans rather than one string, so the line breaks where it
                is written to break. On a phone the tagline does not fit beside
                the mark on one line, and letting it wrap on its own broke it
                mid-clause -- "Play hard. Play / smart. Be first to act."
            -->
            <span class="site-footer__tag">
                <span>Play hard.</span>
                <span>Play smart.</span>
                <span>Be first to act.</span>
            </span>

            <!--
                Two spans, for the same reason: at 375 this wrapped wherever it
                ran out -- "© 2026 First to / Act Poker", splitting the league's
                name across the break.
            -->
            <span class="site-footer__sig">
                <span>&copy; {{ year }}</span>
                <span>First to Act Poker</span>
            </span>
        </div>
    </footer>
</template>
