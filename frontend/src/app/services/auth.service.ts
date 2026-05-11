import { HttpClient } from '@angular/common/http';
import { Injectable, computed, inject, signal } from '@angular/core';
import { catchError, finalize, map, of, tap } from 'rxjs';

export type AuthUser = {
  id_usuario: number;
  uid_usuario: string;
  nombre: string;
  id_autoridad: number | null;
  autoridad: string | null;
  vigencia_usuario: boolean;
};

type LoginResponse = {
  token: string;
  token_type: string;
  user: AuthUser;
};

type MeResponse = {
  user: AuthUser;
};

const TOKEN_KEY = 'bi_auth_token';

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly http = inject(HttpClient);

  private readonly tokenSignal = signal<string | null>(localStorage.getItem(TOKEN_KEY));
  readonly user = signal<AuthUser | null>(null);
  readonly isAuthenticated = computed(() => this.tokenSignal() !== null && this.user() !== null);

  token(): string | null {
    return this.tokenSignal();
  }

  login(uidUsuario: string, pwdUsuario: string) {
    return this.http.post<LoginResponse>('/api/auth/login', {
      uid_usuario: uidUsuario,
      pwd_usuario: pwdUsuario,
    }).pipe(
      tap((res) => this.setSession(res.token, res.user))
    );
  }

  restoreSession() {
    const token = this.tokenSignal();

    if (!token) {
      this.clearSession();
      return of(null);
    }

    return this.http.get<MeResponse>('/api/auth/me').pipe(
      tap((res) => this.user.set(res.user)),
      map((res) => res.user),
      catchError(() => {
        this.clearSession();
        return of(null);
      })
    );
  }

  logout() {
    return this.http.post('/api/auth/logout', {}).pipe(
      catchError(() => of(null)),
      finalize(() => this.clearSession())
    );
  }

  clearSession(): void {
    this.tokenSignal.set(null);
    this.user.set(null);
    localStorage.removeItem(TOKEN_KEY);
  }

  private setSession(token: string, user: AuthUser): void {
    this.tokenSignal.set(token);
    this.user.set(user);
    localStorage.setItem(TOKEN_KEY, token);
  }
}
